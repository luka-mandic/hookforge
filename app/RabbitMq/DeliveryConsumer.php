<?php

namespace App\RabbitMq;

use App\Models\WebhookDelivery;
use App\Services\DeliveryResult;
use App\Services\WebhookDeliveryService;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

final class DeliveryConsumer
{
    private bool $shouldStop = false;

    public function __construct(
        protected WebhookDeliveryService $service,
    ) {}

    /**
     * @param  array<mixed>  $message
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

    /**
     * Consume deliveries until the process receives SIGTERM/SIGINT.
     *
     * Prefetch is 1 so RabbitMQ spreads messages evenly across any number of workers,
     * and a worker never holds unacknowledged messages while it processes another.
     */
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
        $channel->exchange_declare($config->exchange, 'topic', false, true, false);
        $channel->queue_declare($config->queue, false, true, false, false);
        $channel->queue_bind($config->queue, $config->exchange, $config->routingKeyPrefix.'.#');
        $channel->basic_qos(0, 1, false);

        $channel->basic_consume($config->queue, '', false, false, false, false, function (AMQPMessage $message) use ($channel): void {
            try {
                $payload = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);
                $this->handle(is_array($payload) ? $payload : []);
                $channel->basic_ack($message->getDeliveryTag());
            } catch (\Throwable $exception) {
                logger()->warning('Webhook delivery message could not be processed.', [
                    'message' => $message->body,
                    'exception' => $exception->getMessage(),
                ]);

                $channel->basic_nack($message->getDeliveryTag(), false, false);
            }
        });

        $this->listenForShutdown();

        while ($channel->is_consuming() && ! $this->shouldStop) {
            try {
                $channel->wait(null, false, 5);
            } catch (AMQPTimeoutException) {
                // Idle: loop again so a shutdown signal is noticed.
            }
        }

        $channel->close();
        $connection->close();
    }

    private function listenForShutdown(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
    }
}
