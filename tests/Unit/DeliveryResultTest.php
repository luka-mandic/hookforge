<?php

use App\Services\DeliveryResult;

test('success factory builds a successful result', function () {
    $result = DeliveryResult::success(204, 'ok', 15);

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->statusCode)->toBe(204)
        ->and($result->responseBody)->toBe('ok')
        ->and($result->errorMessage)->toBeNull()
        ->and($result->durationMs)->toBe(15);
});

test('failed factory builds an unsuccessful result with details', function () {
    $result = DeliveryResult::failed('HTTP status 500', 500, 'boom', 42);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->statusCode)->toBe(500)
        ->and($result->responseBody)->toBe('boom')
        ->and($result->errorMessage)->toBe('HTTP status 500')
        ->and($result->durationMs)->toBe(42);
});

test('failed result without a response has no status code', function () {
    $result = DeliveryResult::failed('connection refused');

    expect($result->statusCode)->toBeNull()
        ->and($result->responseBody)->toBeNull()
        ->and($result->durationMs)->toBe(0);
});

test('failed result may omit the error message', function () {
    expect(DeliveryResult::failed(null)->errorMessage)->toBeNull();
});
