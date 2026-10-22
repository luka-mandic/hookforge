<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->intruder = User::factory()->create();
    $this->project = Project::factory()->for($this->owner)->create();
    $this->endpoint = WebhookEndpoint::factory()->for($this->project)->create(['name' => 'Original', 'status' => 'active']);
});

test('intruders cannot view, update or delete another users project', function () {
    $this->actingAs($this->intruder)->get(route('projects.show', $this->project))->assertForbidden();
    $this->actingAs($this->intruder)->put(route('projects.update', $this->project), ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($this->intruder)->delete(route('projects.destroy', $this->project))->assertForbidden();

    $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'name' => $this->project->name]);
});

test('intruders cannot update, delete or toggle another users endpoint', function () {
    $this->actingAs($this->intruder)
        ->put(route('endpoints.update', $this->endpoint), ['name' => 'Hacked', 'url' => 'https://evil.test', 'status' => 'active'])
        ->assertForbidden();
    $this->actingAs($this->intruder)->delete(route('endpoints.destroy', $this->endpoint))->assertForbidden();
    $this->actingAs($this->intruder)->post(route('projects.endpoints.toggle', [$this->project, $this->endpoint]))->assertForbidden();

    $this->endpoint->refresh();

    expect($this->endpoint->name)->toBe('Original')
        ->and($this->endpoint->status)->toBe('active');
});

test('intruders cannot read project events or deliveries', function () {
    $this->actingAs($this->intruder)->get(route('projects.events.index', $this->project))->assertForbidden();
    $this->actingAs($this->intruder)->get(route('projects.deliveries.index', $this->project))->assertForbidden();
});

test('intruders cannot view a delivery that belongs to another project', function () {
    $event = WebhookEvent::factory()->for($this->project)->create();
    $delivery = WebhookDelivery::factory()->for($this->project)->for($this->endpoint)->for($event, 'event')->create();

    $this->actingAs($this->intruder)->get(route('deliveries.show', $delivery))->assertForbidden();
    $this->actingAs($this->owner)->get(route('deliveries.show', $delivery))->assertOk();
});

test('intruders cannot publish events to another users project', function () {
    $this->actingAs($this->intruder)
        ->postJson(route('api.v1.projects.events.store', $this->project), ['type' => 'a.b', 'data' => ['x' => 1]])
        ->assertForbidden();

    $this->assertDatabaseCount('webhook_events', 0);
});

test('the global delivery dashboard only lists the current users deliveries', function () {
    $event = WebhookEvent::factory()->for($this->project)->create(['event_type' => 'secret.topic']);
    WebhookDelivery::factory()->for($this->project)->for($this->endpoint)->for($event, 'event')->create(['status' => 'delivered']);

    $this->actingAs($this->intruder)->get(route('deliveries.index'))
        ->assertOk()
        ->assertDontSeeText('secret.topic');

    $this->actingAs($this->owner)->get(route('deliveries.index'))
        ->assertSeeText('secret.topic');
});

test('a user cannot touch an endpoint through a project they own but the endpoint belongs elsewhere', function () {
    $ownProject = Project::factory()->for($this->intruder)->create();

    $this->actingAs($this->intruder)
        ->post(route('projects.endpoints.toggle', [$ownProject, $this->endpoint]))
        ->assertForbidden();
});
