<?php

namespace App\RabbitMq;

use App\Models\WebhookDelivery;
use App\Models\WebhookEvent;
use InvalidArgumentException;

final class WebhookDeliveryMessage extends WebhookMessage
{
    public function __construct(
        string $projectId,
        string $eventId,
        string $eventType,
        array $payload,
        string $routingKey,
        public readonly string $endpointId,
        public readonly string $deliveryId,
        public readonly string $endpointUrl,
    ) {
        parent::__construct(
            $projectId,
            $eventId,
            $eventType,
            $payload,
            $routingKey,
        );
    }

    public static function fromDelivery(WebhookDelivery $delivery, WebhookEvent $event, RabbitMqConfig $config): self
    {
        $endpoint = $delivery->endpoint()->first();

        if (! $endpoint) {
            throw new InvalidArgumentException('Webhook delivery must belong to an endpoint.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $event->payload;
        $payload['event_id'] = $event->getKey();
        $payload['project_id'] = $event->project_id;
        $payload['endpoint_id'] = $delivery->endpoint_id;
        $payload['delivery_id'] = $delivery->getKey();
        $payload['endpoint_url'] = $endpoint->url;

        return new self(
            (string) $event->project_id,
            (string) $event->getKey(),
            $event->event_type,
            $payload,
            $config->routingKeyFor($event->event_type),
            (string) $delivery->endpoint_id,
            (string) $delivery->getKey(),
            $endpoint->url,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'endpoint_id' => $this->endpointId,
            'delivery_id' => $this->deliveryId,
            'endpoint_url' => $this->endpointUrl,
        ];
    }
}
