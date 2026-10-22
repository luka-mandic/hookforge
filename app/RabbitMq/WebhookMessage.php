<?php

namespace App\RabbitMq;

use App\Models\WebhookEvent;
use InvalidArgumentException;

class WebhookMessage
{
    /**
     * @param  array<mixed>  $payload
     */
    public function __construct(
        public readonly string $projectId,
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly array $payload,
        public readonly string $routingKey,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromPayload(array $attributes, RabbitMqConfig $config): self
    {
        $projectId = $attributes['project_id'] ?? null;
        $eventId = $attributes['event_id'] ?? null;
        $eventType = $attributes['event_type'] ?? null;
        $payload = $attributes['payload'] ?? null;

        if (! is_string($projectId) || trim($projectId) === '') {
            throw new InvalidArgumentException('Project id is required to build a webhook message.');
        }

        if (! is_string($eventId) || trim($eventId) === '') {
            throw new InvalidArgumentException('Event id is required to build a webhook message.');
        }

        if (! is_string($eventType) || trim($eventType) === '') {
            throw new InvalidArgumentException('Event type is required to build a webhook message.');
        }

        if (! is_array($payload) || $payload === []) {
            throw new InvalidArgumentException('Webhook payload must be a non-empty array.');
        }

        return new self(
            $projectId,
            $eventId,
            $eventType,
            $payload,
            $config->routingKeyFor($eventType),
        );
    }

    public static function fromEvent(WebhookEvent $event, RabbitMqConfig $config): self
    {
        return self::fromPayload([
            'project_id' => $event->project_id,
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'payload' => $event->payload,
        ], $config);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'project_id' => $this->projectId,
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
        ];
    }
}
