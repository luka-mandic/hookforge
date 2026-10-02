<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\RabbitMq\DeliveryConsumer;
use App\RabbitMq\MessageTransport;
use App\RabbitMq\RabbitMqConfig;
use App\RabbitMq\WebhookMessage;
use App\Services\DefaultRetryPolicy;
use App\Services\DeliveryResult;
use App\Services\WebhookDeliveryService;
use App\Services\WebhookRequestBuilder;
use App\Services\WebhookSigner;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('webhook signer generates the expected hmac signature', function () {
    $payload = [
        'id' => 'evt_123',
        'status' => 'succeeded',
    ];

    $signature = (new WebhookSigner)->sign($payload, 'super-secret');

    expect($signature)->toBe('sha256=' . hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'super-secret'));
});

test('webhook request builder adds the expected metadata', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'super-secret',
        'status' => 'active',
    ]);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
        'payload' => ['id' => 'evt_123', 'amount' => 50],
    ]);
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create([
        'status' => 'queued',
    ]);

    $request = (new WebhookRequestBuilder(new WebhookSigner))->build($delivery);

    expect($request['method'])->toBe('POST')
        ->and($request['url'])->toBe('https://example.com/webhook')
        ->and($request['headers']['Content-Type'])->toBe('application/json')
        ->and($request['headers']['User-Agent'])->toBe('hookforge/1.0')
        ->and($request['headers']['X-Hookforge-Event'])->toBe('invoice.paid')
        ->and($request['headers']['X-Hookforge-Delivery'])->toBe($delivery->id)
        ->and($request['headers']['X-Hookforge-Signature'])->toStartWith('sha256=')
        ->and($request['body'])->toBe(json_encode($event->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
});

test('delivery service records successful deliveries as delivered', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'super-secret',
        'status' => 'active',
    ]);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
        'payload' => ['id' => 'evt_123', 'amount' => 50],
    ]);
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create([
        'status' => 'queued',
        'attempt_count' => 0,
    ]);

    Http::fake([
        'https://example.com/webhook' => Http::response(['ok' => true], 200),
    ]);

    $result = (new WebhookDeliveryService)->process($delivery);

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->statusCode)->toBe(200)
        ->and($delivery->fresh()->status)->toBe('delivered')
        ->and($delivery->fresh()->response_status)->toBe(200)
        ->and($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->fresh()->delivered_at)->not->toBeNull()
        ->and($delivery->attempts()->count())->toBe(1)
        ->and($delivery->attempts()->latest('created_at')->first()->status)->toBe('succeeded');
});

test('delivery service handles failed responses without throwing', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'super-secret',
        'status' => 'active',
    ]);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
        'payload' => ['id' => 'evt_123', 'amount' => 50],
    ]);
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create([
        'status' => 'queued',
        'attempt_count' => 0,
    ]);

    Http::fake([
        'https://example.com/webhook' => Http::response(['error' => 'bad request'], 422),
    ]);

    $result = (new WebhookDeliveryService)->process($delivery);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->statusCode)->toBe(422)
        ->and($delivery->fresh()->status)->toBe('failed')
        ->and($delivery->fresh()->response_status)->toBe(422)
        ->and($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->attempts()->latest('created_at')->first()->status)->toBe('failed');
});

test('delivery service handles network failures without crashing', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'super-secret',
        'status' => 'active',
    ]);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
        'payload' => ['id' => 'evt_123', 'amount' => 50],
    ]);
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create([
        'status' => 'queued',
        'attempt_count' => 0,
    ]);

    Http::fake([
        'https://example.com/webhook' => Http::response('', 500),
    ]);

    $result = (new WebhookDeliveryService)->process($delivery);

    expect($result->isSuccessful())->toBeFalse()
        ->and($delivery->fresh()->status)->toBe('queued')
        ->and($delivery->fresh()->last_error)->toBeString()
        ->and($delivery->fresh()->next_attempt_at)->not->toBeNull()
        ->and($delivery->attempts()->count())->toBe(1);
});

test('delivery consumer processes a message payload by delivery id', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'super-secret',
        'status' => 'active',
    ]);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
        'payload' => ['id' => 'evt_123', 'amount' => 50],
    ]);
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create([
        'status' => 'queued',
        'attempt_count' => 0,
    ]);

    Http::fake([
        'https://example.com/webhook' => Http::response(['ok' => true], 200),
    ]);

    $consumer = new DeliveryConsumer(new WebhookDeliveryService);
    $result = $consumer->handle(['delivery_id' => $delivery->id]);

    expect($result)->toBeInstanceOf(DeliveryResult::class)
        ->and($result->isSuccessful())->toBeTrue()
        ->and($delivery->fresh()->status)->toBe('delivered')
        ->and($delivery->fresh()->attempt_count)->toBe(1);
});

test('delivery processing prevents duplicate claims and reprocessing', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'super-secret',
        'status' => 'active',
    ]);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
        'payload' => ['id' => 'evt_123', 'amount' => 50],
    ]);
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create([
        'status' => 'queued',
        'attempt_count' => 0,
    ]);

    Http::fake([
        'https://example.com/webhook' => Http::response(['ok' => true], 200),
    ]);

    $first = (new WebhookDeliveryService)->process($delivery);
    $second = (new WebhookDeliveryService)->process($delivery->fresh());

    expect($first->isSuccessful())->toBeTrue()
        ->and($second->isSuccessful())->toBeFalse()
        ->and($second->errorMessage)->toContain('already')
        ->and($delivery->fresh()->status)->toBe('delivered')
        ->and($delivery->fresh()->attempt_count)->toBe(1);
});

test('delivery is dead-lettered after the retry budget is exhausted', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'super-secret',
        'status' => 'active',
    ]);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
        'payload' => ['id' => 'evt_123', 'amount' => 50],
    ]);
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create([
        'status' => 'queued',
        'attempt_count' => 0,
    ]);

    $policy = new DefaultRetryPolicy(maxAttempts: 2, initialDelayMs: 1000, maxDelayMs: 1000);
    $service = new WebhookDeliveryService(retryPolicy: $policy);

    Http::fake([
        'https://example.com/webhook' => Http::response(['error' => 'server error'], 500),
    ]);

    $service->process($delivery);
    $delivery->refresh();

    expect($delivery->status)->toBe('queued')
        ->and($delivery->next_attempt_at)->not->toBeNull();

    Http::swap(new class implements MessageTransport
    {
        public function publish(WebhookMessage $message, RabbitMqConfig $config): void
        {
            // noop
        }
    });

    $service->process($delivery->fresh());
    $delivery->refresh();

    expect($delivery->status)->toBe('dead_lettered')
        ->and($delivery->attempt_count)->toBe(2)
        ->and($delivery->next_attempt_at)->toBeNull()
        ->and($delivery->attempts()->count())->toBe(2);
});

test('manual retry requeues a dead-lettered delivery without losing history', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'super-secret',
        'status' => 'active',
    ]);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
        'payload' => ['id' => 'evt_123', 'amount' => 50],
    ]);
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create([
        'status' => 'dead_lettered',
        'attempt_count' => 2,
        'response_status' => 500,
        'last_error' => 'server error',
    ]);

    $service = new WebhookDeliveryService(
        retryPolicy: new DefaultRetryPolicy(maxAttempts: 3, initialDelayMs: 1000, maxDelayMs: 1000),
    );

    $service->retry($delivery);

    expect($delivery->fresh()->status)->toBe('queued')
        ->and($delivery->fresh()->next_attempt_at)->not->toBeNull()
        ->and($delivery->fresh()->last_error)->toBeNull()
        ->and($delivery->attempts()->count())->toBe(0);
});
