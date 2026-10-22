<?php

use App\Models\Project;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\RabbitMq\RabbitMqConfig;
use App\RabbitMq\WebhookDeliveryMessage;
use App\RabbitMq\WebhookMessage;

function mqConfig(): RabbitMqConfig
{
    return RabbitMqConfig::fromArray(['routing_key_prefix' => 'hf.delivery']);
}

test('webhook message is created from a valid payload', function () {
    $message = WebhookMessage::fromPayload([
        'project_id' => 'p1',
        'event_id' => 'e1',
        'event_type' => 'order.created',
        'payload' => ['id' => 1],
    ], mqConfig());

    expect($message->routingKey)->toBe('hf.delivery.order.created')
        ->and($message->toArray())->toBe([
            'project_id' => 'p1',
            'event_id' => 'e1',
            'event_type' => 'order.created',
            'payload' => ['id' => 1],
        ]);
});

test('webhook message rejects missing or blank fields', function (array $override, string $message) {
    $attributes = array_merge([
        'project_id' => 'p1',
        'event_id' => 'e1',
        'event_type' => 'order.created',
        'payload' => ['id' => 1],
    ], $override);

    expect(fn () => WebhookMessage::fromPayload($attributes, mqConfig()))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing project' => [['project_id' => null], 'Project id'],
    'blank event id' => [['event_id' => '  '], 'Event id'],
    'blank event type' => [['event_type' => ''], 'Event type'],
    'empty payload' => [['payload' => []], 'payload'],
    'non array payload' => [['payload' => 'x'], 'payload'],
]);

test('routing key cannot be built from a blank event type', function () {
    mqConfig()->routingKeyFor(' ');
})->throws(InvalidArgumentException::class);

test('rabbitmq config falls back to defaults for missing or invalid values', function () {
    $config = RabbitMqConfig::fromArray(['port' => 'abc', 'exchange' => '  ', 'queue' => 5]);

    expect($config->host)->toBe('rabbitmq')
        ->and($config->port)->toBe(5672)
        ->and($config->exchange)->toBe('hookforge.events')
        ->and($config->queue)->toBe('hookforge.delivery')
        ->and($config->vhost)->toBe('/');
});

test('delivery message carries identifiers and endpoint url but no secret', function () {
    $project = Project::factory()->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create(['url' => 'https://example.com/h', 'secret' => 'shh-secret']);
    $event = WebhookEvent::factory()->for($project)->create(['event_type' => 'order.created', 'payload' => ['id' => 1]]);
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create();

    $message = WebhookDeliveryMessage::fromDelivery($delivery, $event, mqConfig());
    $array = $message->toArray();

    expect($array['delivery_id'])->toBe((string) $delivery->getKey())
        ->and($array['endpoint_id'])->toBe((string) $endpoint->getKey())
        ->and($array['project_id'])->toBe((string) $project->getKey())
        ->and($array['endpoint_url'])->toBe('https://example.com/h')
        ->and($message->routingKey)->toBe('hf.delivery.order.created')
        ->and(json_encode($array))->not->toContain('shh-secret');
});

test('delivery message cannot be built when the endpoint is missing', function () {
    $project = Project::factory()->create();
    $event = WebhookEvent::factory()->for($project)->create(['payload' => ['id' => 1]]);
    $delivery = new WebhookDelivery(['project_id' => $project->getKey(), 'event_id' => $event->getKey(), 'endpoint_id' => 'missing']);

    WebhookDeliveryMessage::fromDelivery($delivery, $event, mqConfig());
})->throws(InvalidArgumentException::class);
