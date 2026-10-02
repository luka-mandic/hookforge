<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('successful event creation', function () {
    $project = Project::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user)
        ->postJson(route('api.v1.projects.events.store', $project), [
            'type' => 'order.created',
            'data' => [
                'order_id' => 'ord_123',
                'amount' => 1299,
            ],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('type', 'order.created')
        ->assertJsonStructure([
            'id',
            'type',
            'data',
        ]);

    $this->assertDatabaseHas('webhook_events', [
        'project_id' => $project->id,
        'event_type' => 'order.created',
    ]);
});

test('invalid payload is rejected', function () {
    $project = Project::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->postJson(route('api.v1.projects.events.store', $project), [
            'data' => [
                'order_id' => 'ord_123',
            ],
        ])
        ->assertJsonValidationErrors(['type']);
});

test('empty event data is rejected', function () {
    $project = Project::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->postJson(route('api.v1.projects.events.store', $project), [
            'type' => 'order.created',
            'data' => [],
        ])
        ->assertJsonValidationErrors(['data']);
});

test('invalid event type is rejected', function () {
    $project = Project::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->postJson(route('api.v1.projects.events.store', $project), [
            'type' => 'order created',
            'data' => ['order_id' => 'ord_123'],
        ])
        ->assertJsonValidationErrors(['type']);
});

test('unauthorized project is rejected', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    $this->actingAs($this->user)
        ->postJson(route('api.v1.projects.events.store', $project), [
            'type' => 'order.created',
            'data' => ['order_id' => 'ord_123'],
        ])
        ->assertForbidden();
});

test('duplicate idempotency key does not create a second event', function () {
    $project = Project::factory()->for($this->user)->create();

    $first = $this->actingAs($this->user)
        ->withHeader('Idempotency-Key', 'evt-123')
        ->postJson(route('api.v1.projects.events.store', $project), [
            'type' => 'order.created',
            'data' => ['order_id' => 'ord_123'],
        ]);

    $first->assertCreated();

    $second = $this->actingAs($this->user)
        ->withHeader('Idempotency-Key', 'evt-123')
        ->postJson(route('api.v1.projects.events.store', $project), [
            'type' => 'order.created',
            'data' => ['order_id' => 'ord_123'],
        ]);

    $second->assertOk();

    $this->assertSame(1, $project->webhookEvents()->where('idempotency_key', 'evt-123')->count());
});
