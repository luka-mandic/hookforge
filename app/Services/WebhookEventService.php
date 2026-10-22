<?php

namespace App\Services;

use App\Models\Project;
use App\Models\WebhookEvent;
use App\RabbitMq\EventPublisher;
use App\Services\Concerns\ReadsAttributes;
use Closure;
use Illuminate\Support\Facades\DB;

class WebhookEventService
{
    use ReadsAttributes;

    public function __construct(
        protected EventPublisher $eventPublisher,
        protected DeliveryService $deliveryService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{event: WebhookEvent, created: bool}
     */
    public function publish(Project $project, array $attributes): array
    {
        $idempotencyKey = $this->resolveIdempotencyKey($attributes);

        if ($idempotencyKey !== null) {
            $existing = $project->webhookEvents()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing instanceof WebhookEvent) {
                return [
                    'event' => $existing,
                    'created' => false,
                ];
            }
        }

        $event = $project->webhookEvents()->create([
            'event_type' => $this->requiredString($attributes, 'type'),
            'payload' => $attributes['data'] ?? [],
            'status' => 'queued',
            'idempotency_key' => $idempotencyKey,
        ]);

        logger()->info('webhook.event.published', [
            'project_id' => $project->getKey(),
            'event_id' => $event->getKey(),
            'event_type' => $event->event_type,
        ]);

        $this->afterCommit('Webhook event publication failed.', $event, fn () => $this->eventPublisher->publish($event));
        $this->afterCommit('Webhook delivery creation failed.', $event, fn () => $this->deliveryService->createForEvent($event));

        return [
            'event' => $event,
            'created' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function resolveIdempotencyKey(array $attributes): ?string
    {
        $value = $attributes['idempotency_key'] ?? null;

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return null;
    }

    /**
     * Side effects run after the surrounding transaction commits (immediately when there is none).
     * Failures are logged, never thrown, so a broker outage cannot fail event ingestion.
     *
     * @param  Closure(): mixed  $callback
     */
    protected function afterCommit(string $failureMessage, WebhookEvent $event, Closure $callback): void
    {
        DB::afterCommit(function () use ($failureMessage, $event, $callback): void {
            try {
                $callback();
            } catch (\Throwable $exception) {
                logger()->warning($failureMessage, [
                    'event_id' => $event->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
