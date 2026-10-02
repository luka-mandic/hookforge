<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use App\RabbitMq\DeliveryPublisher;
use App\RabbitMq\MessageTransport;
use App\RabbitMq\RabbitMqConfig;
use App\RabbitMq\WebhookMessage;
use App\Services\DeliveryService;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('multiple subscribed endpoints create delivery records', function () {
    $project = Project::factory()->for($this->user)->create();
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'order.created',
    ]);

    $first = WebhookEndpoint::factory()->for($project)->create(['status' => 'active']);
    $second = WebhookEndpoint::factory()->for($project)->create(['status' => 'active']);

    WebhookSubscription::factory()->for($project)->for($first)->create([
        'event_type' => 'order.created',
        'status' => 'active',
    ]);
    WebhookSubscription::factory()->for($project)->for($second)->create([
        'event_type' => 'order.created',
        'status' => 'active',
    ]);

    $deliveries = app(DeliveryService::class)->createForEvent($event);

    expect($deliveries)->toHaveCount(2)
        ->and($project->webhookDeliveries()->count())->toBe(2);
});

test('no subscribed endpoints creates no deliveries', function () {
    $project = Project::factory()->for($this->user)->create();
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
    ]);

    $deliveries = app(DeliveryService::class)->createForEvent($event);

    expect($deliveries)->toHaveCount(0)
        ->and($project->webhookDeliveries()->count())->toBe(0);
});

test('disabled endpoints are ignored', function () {
    $project = Project::factory()->for($this->user)->create();
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'customer.created',
    ]);

    $endpoint = WebhookEndpoint::factory()->for($project)->create(['status' => 'disabled']);
    WebhookSubscription::factory()->for($project)->for($endpoint)->create([
        'event_type' => 'customer.created',
        'status' => 'active',
    ]);

    $deliveries = app(DeliveryService::class)->createForEvent($event);

    expect($deliveries)->toHaveCount(0)
        ->and($project->webhookDeliveries()->count())->toBe(0);
});

test('event subscription filtering only includes matching event types', function () {
    $project = Project::factory()->for($this->user)->create();
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'subscription.updated',
    ]);

    $matching = WebhookEndpoint::factory()->for($project)->create(['status' => 'active']);
    $nonMatching = WebhookEndpoint::factory()->for($project)->create(['status' => 'active']);

    WebhookSubscription::factory()->for($project)->for($matching)->create([
        'event_type' => 'subscription.updated',
        'status' => 'active',
    ]);
    WebhookSubscription::factory()->for($project)->for($nonMatching)->create([
        'event_type' => 'order.created',
        'status' => 'active',
    ]);

    $deliveries = app(DeliveryService::class)->createForEvent($event);

    expect($deliveries)->toHaveCount(1)
        ->and($deliveries->first()->endpoint_id)->toBe($matching->id);
});

test('delivery records are stored with the correct event and endpoint identifiers', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create(['status' => 'active']);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'payment.failed',
    ]);

    WebhookSubscription::factory()->for($project)->for($endpoint)->create([
        'event_type' => 'payment.failed',
        'status' => 'active',
    ]);

    app(DeliveryService::class)->createForEvent($event);

    $this->assertDatabaseHas('webhook_deliveries', [
        'project_id' => $project->id,
        'endpoint_id' => $endpoint->id,
        'event_id' => $event->id,
        'status' => 'queued',
    ]);
});

test('delivery messages are published for each queued delivery', function () {
    $transport = new class implements MessageTransport
    {
        public array $messages = [];

        public function publish(WebhookMessage $message, RabbitMqConfig $config): void
        {
            $this->messages[] = [
                'routing_key' => $message->routingKey,
                'payload' => $message->payload,
                'exchange' => $config->exchange,
            ];
        }
    };

    $publisher = new DeliveryPublisher(
        RabbitMqConfig::fromArray([
            'exchange' => 'hookforge.events',
            'routing_key_prefix' => 'hookforge.delivery',
        ]),
        $transport,
    );

    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create(['status' => 'active']);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
    ]);

    WebhookSubscription::factory()->for($project)->for($endpoint)->create([
        'event_type' => 'invoice.paid',
        'status' => 'active',
    ]);

    $delivery = (new DeliveryService($publisher))->createForEvent($event);

    expect($delivery)->toHaveCount(1)
        ->and($transport->messages)->toHaveCount(1)
        ->and($transport->messages[0]['routing_key'])->toBe('hookforge.delivery.invoice.paid')
        ->and($transport->messages[0]['payload']['event_id'])->toBe($event->id);
});
