<?php

namespace App\RabbitMq;

use App\Models\WebhookEvent;

final class EventPublisher
{
    public function __construct(
        public readonly RabbitMqConfig $config,
        protected MessageTransport $transport,
    ) {}

    public function publish(WebhookEvent $event): void
    {
        $message = WebhookMessage::fromEvent($event, $this->config);

        $this->transport->publish($message, $this->config);
    }
}
