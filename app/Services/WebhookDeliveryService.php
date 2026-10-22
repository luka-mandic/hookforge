<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WebhookDeliveryService
{
    public function __construct(
        protected ?WebhookRequestBuilder $requestBuilder = null,
        protected RetryPolicy $retryPolicy = new DefaultRetryPolicy,
    ) {
        $this->requestBuilder ??= new WebhookRequestBuilder(new WebhookSigner);
    }

    public function process(WebhookDelivery $delivery): DeliveryResult
    {
        $claim = DB::transaction(function () use ($delivery): array {
            $current = WebhookDelivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->first();

            if (! $current instanceof WebhookDelivery) {
                return ['state' => 'missing'];
            }

            if (in_array($current->status, ['processing', 'delivered', 'dead_lettered'], true)) {
                return ['state' => 'duplicate'];
            }

            $current->update([
                'status' => 'processing',
                'sent_at' => now(),
                'next_attempt_at' => null,
            ]);

            logger()->info('webhook.delivery.started', [
                'project_id' => $current->project_id,
                'event_id' => $current->event_id,
                'delivery_id' => $current->getKey(),
                'endpoint_id' => $current->endpoint_id,
                'attempt_number' => $current->attempt_count + 1,
            ]);

            return ['state' => 'claimed', 'delivery' => $current->fresh()];
        });

        $claimState = $claim['state'];

        if ($claimState === 'missing') {
            return DeliveryResult::failed('Delivery could not be found.');
        }

        if ($claimState === 'duplicate') {
            return DeliveryResult::failed('Delivery is already being processed or has already been delivered.');
        }

        $lockedDelivery = $claim['delivery'] ?? null;

        if (! $lockedDelivery instanceof WebhookDelivery) {
            return DeliveryResult::failed('Delivery claim could not be established.');
        }

        $request = $this->requestBuilder->build($lockedDelivery);
        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders($request['headers'])
                ->timeout(10)
                ->post($request['url'], json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR));

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $statusCode = $response->status();
            $result = $statusCode >= 200 && $statusCode < 300
                ? DeliveryResult::success($statusCode, $response->body(), $durationMs)
                : DeliveryResult::failed(
                    'HTTP status '.$statusCode,
                    $statusCode,
                    $response->body(),
                    $durationMs,
                );

            $this->persistResult($lockedDelivery, $result);

            return $result;
        } catch (\Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $result = DeliveryResult::failed($exception->getMessage(), null, null, $durationMs);

            $this->persistResult($lockedDelivery, $result);

            return $result;
        }
    }

    public function retry(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery->update([
            'status' => 'queued',
            'response_status' => null,
            'last_error' => null,
            'next_attempt_at' => now(),
            'delivered_at' => null,
        ]);

        logger()->info('webhook.delivery.retried', [
            'project_id' => $delivery->project_id,
            'event_id' => $delivery->event_id,
            'delivery_id' => $delivery->getKey(),
            'endpoint_id' => $delivery->endpoint_id,
            'attempt_number' => $delivery->attempt_count + 1,
        ]);

        return $delivery->fresh();
    }

    protected function persistResult(WebhookDelivery $delivery, DeliveryResult $result): void
    {
        $delivery->increment('attempt_count');
        $delivery->refresh();

        $attemptNumber = $delivery->attempt_count;

        $delivery->attempts()->create([
            'attempt_number' => $attemptNumber,
            'status' => $result->isSuccessful() ? 'succeeded' : 'failed',
            'http_status' => $result->statusCode,
            'response_body' => $result->responseBody,
            'error_message' => $result->errorMessage,
            'started_at' => now()->subMilliseconds($result->durationMs),
            'completed_at' => now(),
        ]);

        if ($result->isSuccessful()) {
            $delivery->update([
                'status' => 'delivered',
                'response_status' => $result->statusCode,
                'last_error' => null,
                'delivered_at' => now(),
                'next_attempt_at' => null,
            ]);

            logger()->info('webhook.delivery.succeeded', [
                'project_id' => $delivery->project_id,
                'event_id' => $delivery->event_id,
                'delivery_id' => $delivery->getKey(),
                'endpoint_id' => $delivery->endpoint_id,
                'attempt_number' => $attemptNumber,
                'http_status' => $result->statusCode,
            ]);

            return;
        }

        if ($this->retryPolicy->shouldRetry($result, $delivery->attempt_count)) {
            $delivery->update([
                'status' => 'queued',
                'response_status' => $result->statusCode,
                'last_error' => $result->errorMessage,
                'next_attempt_at' => now()->addMilliseconds($this->retryPolicy->delayForAttempt($delivery->attempt_count)),
                'delivered_at' => null,
            ]);

            logger()->warning('webhook.delivery.failed', [
                'project_id' => $delivery->project_id,
                'event_id' => $delivery->event_id,
                'delivery_id' => $delivery->getKey(),
                'endpoint_id' => $delivery->endpoint_id,
                'attempt_number' => $attemptNumber,
                'http_status' => $result->statusCode,
                'error' => $result->errorMessage,
            ]);

            logger()->info('webhook.delivery.retried', [
                'project_id' => $delivery->project_id,
                'event_id' => $delivery->event_id,
                'delivery_id' => $delivery->getKey(),
                'endpoint_id' => $delivery->endpoint_id,
                'attempt_number' => $attemptNumber,
                'next_attempt_at' => now()->addMilliseconds($this->retryPolicy->delayForAttempt($delivery->attempt_count)),
            ]);

            return;
        }

        $status = $delivery->attempt_count >= $this->retryPolicy->maxAttempts() ? 'dead_lettered' : 'failed';

        $delivery->update([
            'status' => $status,
            'response_status' => $result->statusCode,
            'last_error' => $result->errorMessage,
            'delivered_at' => null,
            'next_attempt_at' => null,
        ]);

        logger()->warning('webhook.delivery.dead_lettered', [
            'project_id' => $delivery->project_id,
            'event_id' => $delivery->event_id,
            'delivery_id' => $delivery->getKey(),
            'endpoint_id' => $delivery->endpoint_id,
            'attempt_number' => $attemptNumber,
            'http_status' => $result->statusCode,
            'error' => $result->errorMessage,
        ]);
    }
}
