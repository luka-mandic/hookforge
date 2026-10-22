<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\RabbitMq\DeliveryConsumer;
use App\Services\DefaultRetryPolicy;
use App\Services\WebhookDeliveryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function lifecycleDelivery(array $deliveryAttributes = []): WebhookDelivery
{
    $project = Project::factory()->for(User::factory())->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'url' => 'https://example.com/webhook',
        'secret' => 'lifecycle-secret',
        'status' => 'active',
    ]);
    $event = WebhookEvent::factory()->for($project)->create([
        'event_type' => 'invoice.paid',
        'payload' => ['id' => 'evt_1', 'amount' => 50],
    ]);

    return WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create([
        'status' => 'queued',
        'attempt_count' => 0,
        ...$deliveryAttributes,
    ]);
}

function lifecycleService(int $maxAttempts = 3): WebhookDeliveryService
{
    return new WebhookDeliveryService(
        retryPolicy: new DefaultRetryPolicy(maxAttempts: $maxAttempts, initialDelayMs: 1000, maxDelayMs: 4000),
    );
}

test('a successful delivery is signed, delivered and recorded as an attempt', function () {
    Http::fake(['example.com/*' => Http::response('ok', 200)]);
    $delivery = lifecycleDelivery();

    $result = lifecycleService()->process($delivery);

    Http::assertSent(function (Request $request) {
        $signature = $request->header('X-Hookforge-Signature')[0] ?? '';

        return $request->url() === 'https://example.com/webhook'
            && $request->header('X-Hookforge-Event')[0] === 'invoice.paid'
            && $signature === 'sha256='.hash_hmac('sha256', json_encode($request->data(), JSON_UNESCAPED_SLASHES), 'lifecycle-secret');
    });

    $delivery->refresh();
    $attempt = $delivery->attempts()->firstOrFail();

    expect($result->isSuccessful())->toBeTrue()
        ->and($delivery->status)->toBe('delivered')
        ->and($delivery->delivered_at)->not->toBeNull()
        ->and($delivery->next_attempt_at)->toBeNull()
        ->and($delivery->attempt_count)->toBe(1)
        ->and($attempt->status)->toBe('succeeded')
        ->and($attempt->http_status)->toBe(200)
        ->and($attempt->attempt_number)->toBe(1);
});

test('a retryable failure is rescheduled with backoff and keeps the error', function () {
    Http::fake(['example.com/*' => Http::response('down', 503)]);
    $delivery = lifecycleDelivery();

    lifecycleService()->process($delivery);
    $delivery->refresh();

    expect($delivery->status)->toBe('queued')
        ->and($delivery->response_status)->toBe(503)
        ->and($delivery->last_error)->toBe('HTTP status 503')
        ->and($delivery->next_attempt_at)->not->toBeNull()
        ->and($delivery->next_attempt_at->isFuture())->toBeTrue()
        ->and($delivery->delivered_at)->toBeNull();
});

test('a permanent client error is not retried and is not dead-lettered', function () {
    Http::fake(['example.com/*' => Http::response('nope', 404)]);
    $delivery = lifecycleDelivery();

    lifecycleService()->process($delivery);
    $delivery->refresh();

    expect($delivery->status)->toBe('failed')
        ->and($delivery->next_attempt_at)->toBeNull()
        ->and($delivery->attempt_count)->toBe(1);

    Http::assertSentCount(1);
});

test('a connection failure is recorded as a failed attempt and retried', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));
    $delivery = lifecycleDelivery();

    $result = lifecycleService()->process($delivery);
    $delivery->refresh();

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->statusCode)->toBeNull()
        ->and($delivery->status)->toBe('queued')
        ->and($delivery->last_error)->toContain('Connection refused')
        ->and($delivery->attempts()->firstOrFail()->status)->toBe('failed');
});

test('a delivery that recovers after failures ends delivered with full attempt history', function () {
    Http::fake([
        'example.com/*' => Http::sequence()->push('err', 500)->push('err', 502)->push('ok', 200),
    ]);
    $delivery = lifecycleDelivery();
    $service = lifecycleService(maxAttempts: 5);

    foreach (range(1, 3) as $ignored) {
        $service->process($delivery->fresh());
    }

    $delivery->refresh();

    expect($delivery->status)->toBe('delivered')
        ->and($delivery->attempt_count)->toBe(3)
        ->and($delivery->attempts()->orderBy('attempt_number')->pluck('status')->all())
        ->toBe(['failed', 'failed', 'succeeded']);
});

test('retries are exhausted into dead-lettered state after exactly the max attempts', function () {
    Http::fake(['example.com/*' => Http::response('err', 500)]);
    $delivery = lifecycleDelivery();
    $service = lifecycleService(maxAttempts: 3);

    foreach (range(1, 3) as $ignored) {
        $service->process($delivery->fresh());
    }

    $delivery->refresh();

    expect($delivery->status)->toBe('dead_lettered')
        ->and($delivery->attempt_count)->toBe(3)
        ->and($delivery->next_attempt_at)->toBeNull()
        ->and($delivery->last_error)->toBe('HTTP status 500');

    Http::assertSentCount(3);
});

test('a dead-lettered delivery is never sent again', function () {
    Http::fake();
    $delivery = lifecycleDelivery(['status' => 'dead_lettered', 'attempt_count' => 3]);

    $result = lifecycleService()->process($delivery);

    expect($result->isSuccessful())->toBeFalse()
        ->and($delivery->fresh()->attempt_count)->toBe(3);

    Http::assertNothingSent();
});

test('a delivery already being processed is not sent a second time', function () {
    Http::fake();
    $delivery = lifecycleDelivery(['status' => 'processing']);

    $result = lifecycleService()->process($delivery);

    expect($result->errorMessage)->toContain('already');
    Http::assertNothingSent();
});

test('redelivered messages for a delivered delivery are idempotent', function () {
    Http::fake(['example.com/*' => Http::response('ok', 200)]);
    $delivery = lifecycleDelivery();
    $consumer = new DeliveryConsumer(lifecycleService());

    $consumer->handle(['delivery_id' => (string) $delivery->getKey()]);
    $consumer->handle(['delivery_id' => (string) $delivery->getKey()]);
    $consumer->handle(['delivery_id' => (string) $delivery->getKey()]);

    Http::assertSentCount(1);
    expect($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->attempts()->count())->toBe(1);
});

test('the consumer rejects messages with a missing or unknown delivery id', function (array $message) {
    Http::fake();

    $result = (new DeliveryConsumer(lifecycleService()))->handle($message);

    expect($result->isSuccessful())->toBeFalse();
    Http::assertNothingSent();
})->with([
    'no id' => [[]],
    'blank id' => [['delivery_id' => '  ']],
    'non string id' => [['delivery_id' => 123]],
    'unknown id' => [['delivery_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']],
]);

test('manual retry requeues a failed delivery and it can then be delivered', function () {
    Http::fake(['example.com/*' => Http::response('ok', 200)]);
    $delivery = lifecycleDelivery(['status' => 'dead_lettered', 'attempt_count' => 3, 'last_error' => 'boom']);
    $service = lifecycleService();

    $service->retry($delivery);
    expect($delivery->fresh()->status)->toBe('queued');

    $service->process($delivery->fresh());

    expect($delivery->fresh()->status)->toBe('delivered')
        ->and($delivery->fresh()->attempt_count)->toBe(4);
});

test('the response body of a failed attempt is stored for debugging', function () {
    Http::fake(['example.com/*' => Http::response('{"error":"bad"}', 500)]);
    $delivery = lifecycleDelivery();

    lifecycleService()->process($delivery);

    expect($delivery->attempts()->firstOrFail()->response_body)->toBe('{"error":"bad"}');
});
