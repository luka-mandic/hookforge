<?php

namespace App\RabbitMq;

use App\Models\WebhookDelivery;

final class DeliveryPublisher
{
    public function __construct(
        public readonly RabbitMqConfig $config,
        protected MessageTransport $transport,
    ) {}

    public function publish(WebhookDelivery $delivery): void
    {
        $event = $delivery->event()->firstOrFail();
        $message = WebhookDeliveryMessage::fromDelivery($delivery, $event, $this->config);

        $this->transport->publish($message, $this->config);
    }
}
