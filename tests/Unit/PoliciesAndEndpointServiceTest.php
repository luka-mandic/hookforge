<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\ProjectPolicy;
use App\Policies\WebhookEndpointPolicy;
use App\Services\WebhookEndpointService;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->project = Project::factory()->for($this->owner)->create();
});

test('project policy allows only the owner', function () {
    $policy = new ProjectPolicy;

    foreach (['view', 'update', 'delete'] as $ability) {
        expect($policy->{$ability}($this->owner, $this->project))->toBeTrue()
            ->and($policy->{$ability}($this->stranger, $this->project))->toBeFalse();
    }

    expect($policy->create($this->stranger))->toBeTrue();
});

test('endpoint policy allows only the owner of the parent project', function () {
    $endpoint = WebhookEndpoint::factory()->for($this->project)->create();
    $policy = new WebhookEndpointPolicy;

    foreach (['view', 'update', 'delete'] as $ability) {
        expect($policy->{$ability}($this->owner, $endpoint))->toBeTrue()
            ->and($policy->{$ability}($this->stranger, $endpoint))->toBeFalse();
    }

    expect($policy->create($this->owner, $this->project))->toBeTrue()
        ->and($policy->create($this->stranger, $this->project))->toBeFalse();
});

test('endpoint service generates a unique secret per endpoint', function () {
    $service = new WebhookEndpointService;

    $a = $service->create($this->project, ['name' => 'A', 'url' => 'https://a.test']);
    $b = $service->create($this->project, ['name' => 'B', 'url' => 'https://b.test']);

    expect($a->secret)->toHaveLength(32)
        ->and($a->secret)->not->toBe($b->secret)
        ->and($a->status)->toBe('active');
});

test('endpoint service toggles between active and disabled', function () {
    $service = new WebhookEndpointService;
    $endpoint = WebhookEndpoint::factory()->for($this->project)->create(['status' => 'active']);

    expect($service->toggle($endpoint)->status)->toBe('disabled')
        ->and($service->toggle($endpoint)->status)->toBe('active');
});

test('endpoint service update never changes the secret', function () {
    $service = new WebhookEndpointService;
    $endpoint = WebhookEndpoint::factory()->for($this->project)->create(['secret' => 'keep-me']);

    $service->update($endpoint, ['name' => 'Renamed', 'url' => 'https://new.test', 'secret' => 'attacker']);

    expect($endpoint->fresh()->secret)->toBe('keep-me')
        ->and($endpoint->fresh()->name)->toBe('Renamed');
});
