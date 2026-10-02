<?php

namespace App\RabbitMq;

use App\Models\WebhookDelivery;
use App\Services\DeliveryResult;
use App\Services\WebhookDeliveryService;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class DeliveryConsumer
{
    public function __construct(
        protected WebhookDeliveryService $service,
    ) {}

    /**
     * @param  array<string, mixed>  $message
     */
    public function handle(array $message): DeliveryResult
    {
        $deliveryId = $message['delivery_id'] ?? null;

        if (! is_string($deliveryId) || trim($deliveryId) === '') {
            return DeliveryResult::failed('Delivery id is required to process a webhook delivery.');
        }

        $delivery = WebhookDelivery::query()->whereKey($deliveryId)->first();

        if (! $delivery instanceof WebhookDelivery) {
            return DeliveryResult::failed("Delivery {$deliveryId} could not be found.");
        }

        return $this->service->process($delivery);
    }

    public function consume(RabbitMqConfig $config): void
    {
        $connection = new AMQPStreamConnection(
            $config->host,
            $config->port,
            $config->user,
            $config->password,
            $config->vhost,
        );

        $channel = $connection->channel();
        $channel->queue_declare($config->queue, false, true, false, false);

        $channel->basic_consume($config->queue, '', false, false, false, false, function (AMQPMessage $message) use ($channel): void {
            try {
                $payload = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);
                $this->handle((array) $payload);
                $channel->basic_ack($message->delivery_info['delivery_tag']);
            } catch (\Throwable $exception) {
                logger()->warning('Webhook delivery message could not be processed.', [
                    'message' => $message->body,
                    'exception' => $exception->getMessage(),
                ]);

                $channel->basic_nack($message->delivery_info['delivery_tag'], false, false);
            }
        });

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}
