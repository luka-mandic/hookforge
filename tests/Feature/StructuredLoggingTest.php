<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
    file_put_contents(storage_path('logs/laravel.log'), '');
});

test('webhook lifecycle events are logged with structured context and without payload data', function () {
    $eventId = 'evt_123';
    $deliveryId = 'del_456';
    $endpointId = 'ep_789';

    logger()->info('webhook.event.published', [
        'project_id' => $this->project->getKey(),
        'event_id' => $eventId,
        'event_type' => 'invoice.paid',
    ]);

    logger()->info('webhook.delivery.created', [
        'project_id' => $this->project->getKey(),
        'event_id' => $eventId,
        'delivery_id' => $deliveryId,
        'endpoint_id' => $endpointId,
        'attempt_number' => 0,
    ]);

    $logContents = file_get_contents(storage_path('logs/laravel.log')) ?: '';

    expect($logContents)
        ->toContain('webhook.event.published')
        ->and($logContents)->toContain('webhook.delivery.created')
        ->and($logContents)->not->toContain('Ada Lovelace')
        ->and($logContents)->not->toContain('amount')
        ->and($logContents)->toContain($this->project->id);
});
