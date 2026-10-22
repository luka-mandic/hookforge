<?php

use App\Services\DefaultRetryPolicy;
use App\Services\DeliveryResult;

test('permanent client errors are never retried', function (int $status) {
    $policy = new DefaultRetryPolicy(maxAttempts: 5);

    expect($policy->shouldRetry(DeliveryResult::failed("HTTP status {$status}", $status), 1))->toBeFalse();
})->with([400, 401, 403, 404, 405, 410, 422]);

test('transient server errors are retried', function (int $status) {
    $policy = new DefaultRetryPolicy(maxAttempts: 5);

    expect($policy->shouldRetry(DeliveryResult::failed("HTTP status {$status}", $status), 1))->toBeTrue();
})->with([429, 500, 502, 503, 504]);

test('unlisted status codes are not retried', function (int $status) {
    $policy = new DefaultRetryPolicy(maxAttempts: 5);

    expect($policy->shouldRetry(DeliveryResult::failed("HTTP status {$status}", $status), 1))->toBeFalse();
})->with([301, 302, 418, 501]);

test('network errors without a status code are retried', function () {
    $policy = new DefaultRetryPolicy(maxAttempts: 5);

    expect($policy->shouldRetry(DeliveryResult::failed('Connection refused'), 1))->toBeTrue()
        ->and($policy->shouldRetry(DeliveryResult::failed(null), 1))->toBeTrue()
        ->and($policy->shouldRetry(DeliveryResult::failed('cURL error 28: Operation Timed Out'), 1))->toBeTrue();
});

test('retries stop exactly at the attempt budget even for transient failures', function () {
    $policy = new DefaultRetryPolicy(maxAttempts: 3);
    $result = DeliveryResult::failed('HTTP status 503', 503);

    expect($policy->shouldRetry($result, 2))->toBeTrue()
        ->and($policy->shouldRetry($result, 3))->toBeFalse()
        ->and($policy->shouldRetry($result, 4))->toBeFalse();
});

test('constructor clamps invalid configuration to safe values', function () {
    $policy = new DefaultRetryPolicy(maxAttempts: 0, initialDelayMs: -5, maxDelayMs: -1, multiplier: 0);

    expect($policy->maxAttempts())->toBe(1)
        ->and($policy->initialDelayMs)->toBe(0)
        ->and($policy->maxDelayMs)->toBe(0)
        ->and($policy->multiplier)->toBe(2);
});

test('max delay is never lower than the initial delay', function () {
    $policy = new DefaultRetryPolicy(initialDelayMs: 5000, maxDelayMs: 1000);

    expect($policy->maxDelayMs)->toBe(5000)
        ->and($policy->delayForAttempt(3))->toBe(5000);
});

test('delay grows exponentially and never decreases', function () {
    $policy = new DefaultRetryPolicy(maxAttempts: 10, initialDelayMs: 100, maxDelayMs: 100000);

    $delays = array_map(fn (int $attempt) => $policy->delayForAttempt($attempt), range(1, 8));

    expect($delays[0])->toBe(100)
        ->and($delays[1])->toBe(200)
        ->and($delays[2])->toBe(400)
        ->and($delays)->toBe(collect($delays)->sort()->values()->all());
});

test('delay for attempts below one falls back to the initial delay', function () {
    expect((new DefaultRetryPolicy(initialDelayMs: 750))->delayForAttempt(0))->toBe(750);
});
