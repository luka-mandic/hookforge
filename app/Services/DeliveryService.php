<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use App\RabbitMq\DeliveryPublisher;
use Illuminate\Support\Collection;

class DeliveryService
{
    public function __construct(
        protected DeliveryPublisher $publisher,
    ) {}

    /**
     * @return Collection<int, WebhookDelivery>
     */
    public function createForEvent(WebhookEvent $event): Collection
    {
        $deliveries = $this->matchingEndpoints($event)
            ->map(fn (WebhookEndpoint $endpoint) => $this->createDelivery($event, $endpoint))
            ->values();

        $this->publishDeliveries($deliveries);

        return $deliveries;
    }

    /**
     * @return Collection<int, WebhookEndpoint>
     */
    protected function matchingEndpoints(WebhookEvent $event): Collection
    {
        return $event->project()->firstOrFail()
            ->webhookSubscriptions()
            ->where('event_type', $event->event_type)
            ->where('status', 'active')
            ->with('endpoint')
            ->get()
            ->map(fn (WebhookSubscription $subscription) => $subscription->endpoint)
            ->filter(fn (?WebhookEndpoint $endpoint): bool => $endpoint !== null && $endpoint->isEnabled())
            ->unique('id')
            ->values();
    }

    protected function createDelivery(WebhookEvent $event, WebhookEndpoint $endpoint): WebhookDelivery
    {
        $delivery = $event->project()->firstOrFail()->webhookDeliveries()->create([
            'project_id' => $event->project_id,
            'endpoint_id' => $endpoint->getKey(),
            'event_id' => $event->getKey(),
            'status' => 'queued',
            'attempt_count' => 0,
        ]);

        logger()->info('webhook.delivery.created', [
            'project_id' => $event->project_id,
            'event_id' => $event->getKey(),
            'delivery_id' => $delivery->getKey(),
            'endpoint_id' => $endpoint->getKey(),
            'attempt_number' => 0,
        ]);

        return $delivery->refresh();
    }

    /**
     * @param  Collection<int, WebhookDelivery>  $deliveries
     */
    protected function publishDeliveries(Collection $deliveries): void
    {
        foreach ($deliveries as $delivery) {
            try {
                $this->publisher->publish($delivery);
            } catch (\Throwable $exception) {
                logger()->warning('Webhook delivery publication failed.', [
                    'delivery_id' => $delivery->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
