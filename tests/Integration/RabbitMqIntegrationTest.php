<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\RabbitMq\AmqpMessageTransport;
use App\RabbitMq\DeliveryConsumer;
use App\RabbitMq\DeliveryPublisher;
use App\RabbitMq\RabbitMqConfig;
use App\Services\WebhookDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;

uses(RefreshDatabase::class);

/*
 * Requires a reachable RabbitMQ. Override with INTEGRATION_RABBITMQ_HOST,
 * INTEGRATION_RABBITMQ_PORT, INTEGRATION_RABBITMQ_USER and INTEGRATION_RABBITMQ_PASSWORD.
 * Each test uses a uniquely named exchange and queue and removes them afterwards.
 */
beforeEach(function () {
    $host = env('INTEGRATION_RABBITMQ_HOST', '127.0.0.1');
    $port = (int) env('INTEGRATION_RABBITMQ_PORT', 5672);

    $reachable = @fsockopen($host, $port, $errno, $errstr, 0.5);

    if ($reachable === false) {
        $this->markTestSkipped("RabbitMQ is not reachable at {$host}:{$port}.");
    }

    fclose($reachable);

    $suffix = Str::lower(Str::random(10));

    $this->mq = new RabbitMqConfig(
        host: $host,
        port: $port,
        user: env('INTEGRATION_RABBITMQ_USER', 'guest'),
        password: env('INTEGRATION_RABBITMQ_PASSWORD', 'guest'),
        vhost: '/',
        exchange: "hookforge.it.{$suffix}",
        routingKeyPrefix: 'hookforge.it',
        queue: "hookforge.it.queue.{$suffix}",
    );

    $this->connection = new AMQPStreamConnection($this->mq->host, $this->mq->port, $this->mq->user, $this->mq->password, $this->mq->vhost);
    $this->channel = $this->connection->channel();
});

afterEach(function () {
    if (isset($this->channel)) {
        $this->channel->queue_delete($this->mq->queue);
        $this->channel->exchange_delete($this->mq->exchange);
        $this->channel->close();
        $this->connection->close();
    }
});

function integrationDelivery(): WebhookDelivery
{
    $project = Project::factory()->for(User::factory())->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create(['url' => 'https://example.com/hook', 'secret' => 'it-secret']);
    $event = WebhookEvent::factory()->for($project)->create(['event_type' => 'order.created', 'payload' => ['id' => 7]]);

    return WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')
        ->create(['status' => 'queued', 'attempt_count' => 0]);
}

test('a delivery message is published to the queue with identifiers and no secret', function () {
    $delivery = integrationDelivery();

    (new DeliveryPublisher($this->mq, new AmqpMessageTransport))->publish($delivery);

    $message = $this->channel->basic_get($this->mq->queue, true);

    expect($message)->not->toBeNull();

    $body = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);

    expect($body['delivery_id'])->toBe((string) $delivery->getKey())
        ->and($body['endpoint_id'])->toBe((string) $delivery->endpoint_id)
        ->and($body['event_type'])->toBe('order.created')
        ->and($message->body)->not->toContain('it-secret')
        ->and($message->get('delivery_mode'))->toBe(2)
        ->and($message->getRoutingKey())->toBe('hookforge.it.order.created');
});

test('a published message can be consumed and results in a delivered webhook', function () {
    Http::fake(['example.com/*' => Http::response('ok', 200)]);
    $delivery = integrationDelivery();

    (new DeliveryPublisher($this->mq, new AmqpMessageTransport))->publish($delivery);

    $message = $this->channel->basic_get($this->mq->queue, true);
    $result = (new DeliveryConsumer(new WebhookDeliveryService))
        ->handle(json_decode($message->body, true, 512, JSON_THROW_ON_ERROR));

    expect($result->isSuccessful())->toBeTrue()
        ->and($delivery->fresh()->status)->toBe('delivered');
});

test('the same message delivered twice results in a single webhook request', function () {
    Http::fake(['example.com/*' => Http::response('ok', 200)]);
    $delivery = integrationDelivery();
    $publisher = new DeliveryPublisher($this->mq, new AmqpMessageTransport);

    $publisher->publish($delivery);
    $publisher->publish($delivery);

    $consumer = new DeliveryConsumer(new WebhookDeliveryService);

    foreach (range(1, 2) as $ignored) {
        $message = $this->channel->basic_get($this->mq->queue, true);
        $consumer->handle(json_decode($message->body, true, 512, JSON_THROW_ON_ERROR));
    }

    Http::assertSentCount(1);
    expect($delivery->fresh()->attempt_count)->toBe(1);
});

test('publishing to an unreachable broker throws so callers can handle it', function () {
    $unreachable = new RabbitMqConfig('127.0.0.1', 1, 'guest', 'guest', '/', 'x', 'x', 'x');

    $this->expectException(Throwable::class);

    (new DeliveryPublisher($unreachable, new AmqpMessageTransport))->publish(integrationDelivery());
});
