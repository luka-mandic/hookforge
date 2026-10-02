<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\RabbitMq\AmqpMessageTransport;
use App\RabbitMq\DeliveryPublisher;
use App\RabbitMq\RabbitMqConfig;
use Illuminate\Support\Collection;

class DeliveryService
{
    public function __construct(
        protected ?DeliveryPublisher $publisher = null,
    ) {
        $this->publisher ??= new DeliveryPublisher(
            RabbitMqConfig::fromEnvironment(),
            new AmqpMessageTransport,
        );
    }

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
            ->map(fn ($subscription) => $subscription->endpoint)
            ->filter(fn (?WebhookEndpoint $endpoint) => $endpoint !== null && $endpoint->isEnabled())
            ->unique('id')
            ->values();
    }

    protected function createDelivery(WebhookEvent $event, WebhookEndpoint $endpoint): WebhookDelivery
    {
        $delivery = $event->project->webhookDeliveries()->create([
            'project_id' => $event->project_id,
            'endpoint_id' => $endpoint->getKey(),
            'event_id' => $event->getKey(),
            'status' => 'queued',
            'attempt_count' => 0,
        ]);

        return $delivery->fresh();
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
