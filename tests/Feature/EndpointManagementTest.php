<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEndpoint;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

test('an endpoint can be created with a generated secret', function () {
    $this->actingAs($this->user)
        ->post(route('projects.endpoints.store', $this->project), [
            'name' => 'Billing', 'url' => 'https://example.com/hook', 'status' => 'active',
        ])
        ->assertRedirect(route('projects.show', $this->project));

    $endpoint = WebhookEndpoint::query()->where('name', 'Billing')->firstOrFail();

    expect($endpoint->secret)->not->toBeEmpty()
        ->and($endpoint->project_id)->toBe($this->project->id);
});

test('the generated secret is flashed nowhere and not shown on the project page', function () {
    $this->actingAs($this->user)
        ->post(route('projects.endpoints.store', $this->project), [
            'name' => 'Billing', 'url' => 'https://example.com/hook', 'status' => 'active',
        ])
        ->assertSessionMissing('secret');

    $secret = WebhookEndpoint::query()->firstOrFail()->secret;

    $this->actingAs($this->user)->get(route('projects.show', $this->project))->assertDontSee($secret);
});

test('endpoint creation rejects invalid input', function (array $input, string $field) {
    $this->actingAs($this->user)
        ->post(route('projects.endpoints.store', $this->project), $input)
        ->assertSessionHasErrors($field);

    expect(WebhookEndpoint::query()->count())->toBe(0);
})->with([
    'missing name' => [['url' => 'https://example.com', 'status' => 'active'], 'name'],
    'bad url' => [['name' => 'x', 'url' => 'nope', 'status' => 'active'], 'url'],
    'non http scheme' => [['name' => 'x', 'url' => 'ftp://example.com', 'status' => 'active'], 'url'],
    'bad status' => [['name' => 'x', 'url' => 'https://example.com', 'status' => 'weird'], 'status'],
    'overlong name' => [['name' => str_repeat('a', 256), 'url' => 'https://example.com', 'status' => 'active'], 'name'],
]);

test('an endpoint can be updated but its secret is preserved', function () {
    $endpoint = WebhookEndpoint::factory()->for($this->project)->create(['secret' => 'original-secret']);

    $this->actingAs($this->user)
        ->put(route('endpoints.update', $endpoint), [
            'name' => 'Renamed', 'url' => 'https://new.example.com', 'status' => 'paused', 'secret' => 'injected',
        ])
        ->assertRedirect();

    $endpoint->refresh();

    expect($endpoint->name)->toBe('Renamed')
        ->and($endpoint->status)->toBe('paused')
        ->and($endpoint->secret)->toBe('original-secret');
});

test('an endpoint can be deleted', function () {
    $endpoint = WebhookEndpoint::factory()->for($this->project)->create();

    $this->actingAs($this->user)->delete(route('endpoints.destroy', $endpoint))->assertRedirect();

    $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
});

test('toggling twice returns an endpoint to its original status', function () {
    $endpoint = WebhookEndpoint::factory()->for($this->project)->create(['status' => 'active']);
    $url = route('projects.endpoints.toggle', [$this->project, $endpoint]);

    $this->actingAs($this->user)->post($url);
    expect($endpoint->fresh()->status)->toBe('disabled');

    $this->actingAs($this->user)->post($url);
    expect($endpoint->fresh()->status)->toBe('active');
});
