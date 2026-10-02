<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WebhookDeliveryService
{
    public function __construct(
        protected ?WebhookRequestBuilder $requestBuilder = null,
        protected RetryPolicy $retryPolicy = new DefaultRetryPolicy(maxAttempts: 1),
    ) {
        $this->requestBuilder ??= new WebhookRequestBuilder(new WebhookSigner);
    }

    public function process(WebhookDelivery $delivery): DeliveryResult
    {
        $lockedDelivery = DB::transaction(function () use ($delivery): ?WebhookDelivery {
            $current = WebhookDelivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->first();

            if (! $current instanceof WebhookDelivery) {
                return null;
            }

            if (in_array($current->status, ['processing', 'delivered'], true)) {
                return $current;
            }

            $current->update([
                'status' => 'processing',
                'sent_at' => now(),
            ]);

            return $current->fresh();
        });

        if (! $lockedDelivery instanceof WebhookDelivery) {
            return DeliveryResult::failed('Delivery could not be found.');
        }

        if ($lockedDelivery->status === 'delivered' && $lockedDelivery->attempt_count > 0) {
            return DeliveryResult::failed('Delivery has already been processed.');
        }

        if ($lockedDelivery->status === 'processing' && $lockedDelivery->attempt_count > 0) {
            return DeliveryResult::failed('Delivery is already being processed.');
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
                    'HTTP status ' . $statusCode,
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

    protected function persistResult(WebhookDelivery $delivery, DeliveryResult $result): void
    {
        $delivery->increment('attempt_count');
        $delivery->refresh();

        $delivery->attempts()->create([
            'attempt_number' => $delivery->attempt_count,
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

            return;
        }

        $delivery->update([
            'status' => 'failed',
            'response_status' => $result->statusCode,
            'last_error' => $result->errorMessage,
            'delivered_at' => null,
            'next_attempt_at' => null,
        ]);
    }
}
