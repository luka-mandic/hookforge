<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEndpoint;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guest users are redirected to login when trying to access project management', function () {
    $response = $this->get(route('projects.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can create a project', function () {
    $this->actingAs($this->user)
        ->post(route('projects.store'), [
            'name' => 'Acme Payments',
            'description' => 'Primary billing project.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'user_id' => $this->user->id,
        'name' => 'Acme Payments',
    ]);
});

test('users can only manage their own projects', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    $this->actingAs($this->user)
        ->get(route('projects.show', $project))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->post(route('projects.endpoints.store', $project), [
            'name' => 'Invoices',
            'url' => 'https://example.com/webhook',
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('webhook endpoint urls are validated and secrets are hidden after creation', function () {
    $project = Project::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user)
        ->post(route('projects.endpoints.store', $project), [
            'name' => 'Invalid Endpoint',
            'url' => 'not-a-valid-url',
            'status' => 'active',
        ]);

    $response->assertSessionHasErrors(['url']);

    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'secret' => 'secret-value',
    ]);

    $this->actingAs($this->user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSeeText('secret-value');

    $this->assertDatabaseHas('webhook_endpoints', [
        'id' => $endpoint->id,
        'secret' => 'secret-value',
    ]);
});

test('users can enable and disable webhook endpoints', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create([
        'status' => 'active',
    ]);

    $this->actingAs($this->user)
        ->post(route('projects.endpoints.toggle', [$project, $endpoint]))
        ->assertRedirect();

    $endpoint->refresh();

    expect($endpoint->status)->toBe('disabled');
});
