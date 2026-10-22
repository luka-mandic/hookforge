<?php

namespace App\Services;

use App\Models\Project;
use App\Models\WebhookEvent;
use App\RabbitMq\EventPublisher;
use Illuminate\Support\Facades\DB;

class WebhookEventService
{
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
            'event_type' => (string) $attributes['type'],
            'payload' => $attributes['data'],
            'status' => 'queued',
            'idempotency_key' => $idempotencyKey,
        ]);

        logger()->info('webhook.event.published', [
            'project_id' => $project->getKey(),
            'event_id' => $event->getKey(),
            'event_type' => $event->event_type,
        ]);

        if (app()->bound(EventPublisher::class)) {
            DB::afterCommit(function () use ($event) {
                try {
                    app(EventPublisher::class)->publish($event);
                } catch (\Throwable $exception) {
                    logger()->warning('Webhook event publication failed.', [
                        'event_id' => $event->getKey(),
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
        }

        if (app()->bound(DeliveryService::class)) {
            if (DB::transactionLevel() > 0) {
                DB::afterCommit(function () use ($event) {
                    try {
                        app(DeliveryService::class)->createForEvent($event);
                    } catch (\Throwable $exception) {
                        logger()->warning('Webhook delivery creation failed.', [
                            'event_id' => $event->getKey(),
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                });
            } else {
                try {
                    app(DeliveryService::class)->createForEvent($event);
                } catch (\Throwable $exception) {
                    logger()->warning('Webhook delivery creation failed.', [
                        'event_id' => $event->getKey(),
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        }

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
}
