<?php

namespace App\RabbitMq;

interface MessageTransport
{
    public function publish(WebhookMessage $message, RabbitMqConfig $config): void;
}
