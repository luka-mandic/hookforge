<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\DefaultRetryPolicy;
use App\Services\DeliveryResult;
use App\Services\WebhookDeliveryService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('retry policy calculates exponential backoff with max delay cap', function () {
    $policy = new DefaultRetryPolicy(
        maxAttempts: 5,
        initialDelayMs: 1000,
        maxDelayMs: 5000,
        multiplier: 2,
    );

    expect($policy->delayForAttempt(1))->toBe(1000)
        ->and($policy->delayForAttempt(2))->toBe(2000)
        ->and($policy->delayForAttempt(4))->toBe(5000)
        ->and($policy->delayForAttempt(6))->toBe(5000);
});

test('retry policy respects maximum attempts', function () {
    $policy = new DefaultRetryPolicy(maxAttempts: 3, initialDelayMs: 1000, maxDelayMs: 5000);

    expect($policy->shouldRetry(DeliveryResult::failed('timeout', null, null, 0), 1))->toBeTrue()
        ->and($policy->shouldRetry(DeliveryResult::failed('timeout', null, null, 0), 2))->toBeTrue()
        ->and($policy->shouldRetry(DeliveryResult::failed('timeout', null, null, 0), 3))->toBeFalse();
});

test('retry policy retries transient failures and ignores permanent ones', function () {
    $policy = new DefaultRetryPolicy(maxAttempts: 5, initialDelayMs: 1000, maxDelayMs: 5000);

    expect($policy->shouldRetry(DeliveryResult::failed('HTTP status 429', 429, 'too many requests', 0), 1))->toBeTrue()
        ->and($policy->shouldRetry(DeliveryResult::failed('HTTP status 500', 500, 'server error', 0), 1))->toBeTrue()
        ->and($policy->shouldRetry(DeliveryResult::failed('HTTP status 422', 422, 'bad input', 0), 1))->toBeFalse()
        ->and($policy->shouldRetry(DeliveryResult::failed('timed out', null, null, 0), 1))->toBeTrue();
});

test('webhook delivery service schedules retries instead of final failure for retryable responses', function () {
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
        'https://example.com/webhook' => Http::response(['error' => 'gateway timeout'], 504),
    ]);

    $service = new WebhookDeliveryService(
        retryPolicy: new DefaultRetryPolicy(maxAttempts: 5, initialDelayMs: 1000, maxDelayMs: 10000),
    );

    $result = $service->process($delivery);

    expect($result->isSuccessful())->toBeFalse()
        ->and($delivery->fresh()->status)->toBe('queued')
        ->and($delivery->fresh()->next_attempt_at)->not->toBeNull()
        ->and($delivery->fresh()->attempt_count)->toBe(1)
        ->and($delivery->attempts()->count())->toBe(1);
});
