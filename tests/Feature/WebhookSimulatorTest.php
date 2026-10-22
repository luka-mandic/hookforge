<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEndpoint;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

test('developers can create a simulated webhook endpoint for a chosen status code', function () {
    $response = $this->actingAs($this->user)
        ->post(route('projects.test-endpoints.store', $this->project), [
            'response_status' => 429,
            'delay_ms' => 250,
        ]);

    $response->assertRedirect(route('projects.show', $this->project));

    $endpoint = WebhookEndpoint::query()
        ->where('project_id', $this->project->id)
        ->where('name', 'like', '%Test endpoint%')
        ->first();

    expect($endpoint)->not->toBeNull()
        ->and($endpoint->status)->toBe('active')
        ->and($endpoint->url)->toContain('/projects/'.$this->project->id.'/test-endpoints/');
});

test('the simulated webhook endpoint returns the configured status and delay without blocking excessively', function () {
    $response = $this->actingAs($this->user)
        ->post(route('projects.test-endpoints.store', $this->project), [
            'response_status' => 201,
            'delay_ms' => 50,
        ]);

    $endpoint = WebhookEndpoint::query()
        ->where('project_id', $this->project->id)
        ->where('name', 'like', '%Test endpoint%')
        ->firstOrFail();

    $this->get($endpoint->url)->assertStatus(201);
});
