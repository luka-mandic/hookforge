<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
    $this->payload = ['type' => 'order.created', 'data' => ['order_id' => 'ord_1']];
});

function publishWithKey(object $test, Project $project, array $payload, ?string $key): TestResponse
{
    $test->withHeader('Idempotency-Key', $key ?? '');

    return $test->actingAs($test->user)->postJson(route('api.v1.projects.events.store', $project), $payload);
}

test('replaying a request returns the original event instead of creating a new one', function () {
    $first = publishWithKey($this, $this->project, $this->payload, 'key-1')->assertCreated();
    $second = publishWithKey($this, $this->project, $this->payload, 'key-1')->assertOk();

    expect($second->json('id'))->toBe($first->json('id'))
        ->and($this->project->webhookEvents()->count())->toBe(1);
});

test('idempotency keys can also be sent in the request body', function () {
    $body = [...$this->payload, 'idempotency_key' => 'body-key'];

    $this->actingAs($this->user)->postJson(route('api.v1.projects.events.store', $this->project), $body)->assertCreated();
    $this->actingAs($this->user)->postJson(route('api.v1.projects.events.store', $this->project), $body)->assertOk();

    expect($this->project->webhookEvents()->count())->toBe(1);
});

test('a replay does not create additional deliveries', function () {
    $endpoint = WebhookEndpoint::factory()->for($this->project)->create(['status' => 'active']);
    WebhookSubscription::factory()->for($this->project)->for($endpoint)->create(['event_type' => 'order.created', 'status' => 'active']);

    publishWithKey($this, $this->project, $this->payload, 'key-2')->assertCreated();
    publishWithKey($this, $this->project, $this->payload, 'key-2')->assertOk();

    expect($this->project->webhookDeliveries()->count())->toBe(1);
});

test('the same key can be used in different projects', function () {
    $other = Project::factory()->for($this->user)->create();

    publishWithKey($this, $this->project, $this->payload, 'shared')->assertCreated();
    publishWithKey($this, $other, $this->payload, 'shared')->assertCreated();

    expect($this->project->webhookEvents()->count())->toBe(1)
        ->and($other->webhookEvents()->count())->toBe(1);
});

test('different keys create different events', function () {
    publishWithKey($this, $this->project, $this->payload, 'a')->assertCreated();
    publishWithKey($this, $this->project, $this->payload, 'b')->assertCreated();

    expect($this->project->webhookEvents()->count())->toBe(2);
});

test('requests without a key are not deduplicated', function () {
    $url = route('api.v1.projects.events.store', $this->project);

    $this->actingAs($this->user)->postJson($url, $this->payload)->assertCreated();
    $this->actingAs($this->user)->postJson($url, $this->payload)->assertCreated();

    expect($this->project->webhookEvents()->count())->toBe(2);
});

test('an overlong idempotency key is rejected', function () {
    publishWithKey($this, $this->project, $this->payload, str_repeat('k', 256))
        ->assertJsonValidationErrors('idempotency_key');

    expect($this->project->webhookEvents()->count())->toBe(0);
});

test('a replay is not logged as a new publication', function () {
    Log::spy();

    publishWithKey($this, $this->project, $this->payload, 'log-key');
    publishWithKey($this, $this->project, $this->payload, 'log-key');

    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => $message === 'webhook.event.published')->once();
});
