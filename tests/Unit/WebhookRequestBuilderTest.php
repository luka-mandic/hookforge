<?php

use App\Models\Project;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\WebhookRequestBuilder;
use App\Services\WebhookSigner;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function makeBuilderDelivery(array $payload, string $secret = 'builder-secret', string $url = 'https://example.com/hook'): WebhookDelivery
{
    $project = Project::factory()->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create(['url' => $url, 'secret' => $secret]);
    $event = WebhookEvent::factory()->for($project)->create(['event_type' => 'invoice.paid', 'payload' => $payload]);

    return WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create();
}

test('request targets the endpoint url with POST and a json body', function () {
    $delivery = makeBuilderDelivery(['id' => 'evt_1', 'link' => 'https://example.com/x']);

    $request = (new WebhookRequestBuilder)->build($delivery);

    expect($request['method'])->toBe('POST')
        ->and($request['url'])->toBe('https://example.com/hook')
        ->and($request['body'])->toBe('{"id":"evt_1","link":"https://example.com/x"}')
        ->and($request['headers']['Content-Type'])->toBe('application/json');
});

test('request carries event, delivery and signature headers', function () {
    $delivery = makeBuilderDelivery(['id' => 'evt_1']);

    $headers = (new WebhookRequestBuilder)->build($delivery)['headers'];

    expect($headers['X-Hookforge-Event'])->toBe('invoice.paid')
        ->and($headers['X-Hookforge-Delivery'])->toBe((string) $delivery->getKey())
        ->and($headers['User-Agent'])->toBe('hookforge/1.0');
});

test('signature header verifies against the exact body that is sent', function () {
    $delivery = makeBuilderDelivery(['id' => 'evt_1', 'n' => [1, 2]], 'builder-secret');

    $request = (new WebhookRequestBuilder(new WebhookSigner))->build($delivery);

    expect($request['headers']['X-Hookforge-Signature'])
        ->toBe('sha256='.hash_hmac('sha256', $request['body'], 'builder-secret'));
});

test('a different endpoint secret yields a different signature', function () {
    $a = (new WebhookRequestBuilder)->build(makeBuilderDelivery(['id' => 1], 'secret-a'));
    $b = (new WebhookRequestBuilder)->build(makeBuilderDelivery(['id' => 1], 'secret-b'));

    expect($a['headers']['X-Hookforge-Signature'])->not->toBe($b['headers']['X-Hookforge-Signature']);
});

test('the endpoint secret is never placed in the request itself', function () {
    $request = (new WebhookRequestBuilder)->build(makeBuilderDelivery(['id' => 1], 'very-secret-value'));

    expect(json_encode($request))->not->toContain('very-secret-value');
});

test('building fails for a delivery whose event no longer exists', function () {
    $delivery = makeBuilderDelivery(['id' => 1]);
    WebhookEvent::query()->whereKey($delivery->event_id)->delete();

    (new WebhookRequestBuilder)->build($delivery->fresh() ?? $delivery);
})->throws(ModelNotFoundException::class);
