<?php

use App\Models\DeliveryAttempt;
use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;

test('a user has many projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    expect($user->projects()->whereKey($project->id)->exists())->toBeTrue();
    expect($user->projects)->toHaveCount(1);
});

test('a project owns endpoints, subscriptions, events, and deliveries', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create();
    $subscription = WebhookSubscription::factory()->for($project)->for($endpoint)->create();
    $event = WebhookEvent::factory()->for($project)->create();
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event)->create();
    $attempt = DeliveryAttempt::factory()->for($delivery)->create();

    expect($project->webhookEndpoints()->whereKey($endpoint->id)->exists())->toBeTrue();
    expect($endpoint->subscriptions()->whereKey($subscription->id)->exists())->toBeTrue();
    expect($project->webhookEvents()->whereKey($event->id)->exists())->toBeTrue();
    expect($event->deliveries()->whereKey($delivery->id)->exists())->toBeTrue();
    expect($delivery->attempts()->whereKey($attempt->id)->exists())->toBeTrue();
});
