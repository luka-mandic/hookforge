<?php

namespace App\RabbitMq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class AmqpMessageTransport implements MessageTransport
{
    public function publish(WebhookMessage $message, RabbitMqConfig $config): void
    {
        $connection = new AMQPStreamConnection(
            $config->host,
            $config->port,
            $config->user,
            $config->password,
            $config->vhost,
        );

        $channel = $connection->channel();

        $channel->exchange_declare($config->exchange, 'topic', false, true, false);
        $channel->queue_declare($config->queue, false, true, false, false);
        $channel->queue_bind($config->queue, $config->exchange, $message->routingKey);

        $body = json_encode($message->toArray(), JSON_THROW_ON_ERROR);
        $envelope = new AMQPMessage($body, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        $channel->basic_publish($envelope, $config->exchange, $message->routingKey);

        $channel->close();
        $connection->close();
    }
}
