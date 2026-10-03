<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('authenticated users can filter delivery records by status, event type, endpoint, project, and date range', function () {
    $projectA = Project::factory()->for($this->user)->create(['name' => 'Alpha Project']);
    $projectB = Project::factory()->for($this->user)->create(['name' => 'Beta Project']);

    $billingEndpoint = WebhookEndpoint::factory()->for($projectA)->create(['name' => 'Billing Endpoint']);
    $legacyEndpoint = WebhookEndpoint::factory()->for($projectB)->create(['name' => 'Legacy Endpoint']);

    $invoiceEvent = WebhookEvent::factory()->for($projectA)->create(['event_type' => 'invoice.paid']);
    $customerEvent = WebhookEvent::factory()->for($projectB)->create(['event_type' => 'customer.created']);

    $matchingDelivery = WebhookDelivery::factory()->for($projectA)->for($billingEndpoint, 'endpoint')->for($invoiceEvent, 'event')->create([
        'status' => 'delivered',
        'created_at' => now()->subDay(),
    ]);

    WebhookDelivery::factory()->for($projectA)->for($billingEndpoint, 'endpoint')->for($customerEvent, 'event')->create([
        'status' => 'failed',
        'created_at' => now()->subWeek(),
    ]);

    WebhookDelivery::factory()->for($projectB)->for($legacyEndpoint, 'endpoint')->for($customerEvent, 'event')->create([
        'status' => 'delivered',
        'created_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('deliveries.index', [
            'project' => $projectA->id,
            'endpoint' => $billingEndpoint->id,
            'event_type' => 'invoice.paid',
            'status' => 'delivered',
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

    $response->assertOk();
    $response->assertSeeText($billingEndpoint->name);
    $response->assertSeeText('invoice.paid');
    $response->assertDontSeeText('Legacy Endpoint');
    $response->assertDontSeeText('customer.created');
    $response->assertDontSeeText('failed');
});

test('delivery dashboard supports pagination while preserving filter query strings', function () {
    $project = Project::factory()->for($this->user)->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create(['name' => 'Paginated Endpoint']);
    $event = WebhookEvent::factory()->for($project)->create(['event_type' => 'invoice.paid']);

    WebhookDelivery::factory()->count(25)->for($project)->for($endpoint, 'endpoint')->for($event, 'event')->create([
        'status' => 'delivered',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('deliveries.index', [
            'status' => 'delivered',
            'event_type' => 'invoice.paid',
            'page' => 2,
        ]));

    $response->assertOk();
    $response->assertSee('status=delivered');
    $response->assertSee('event_type=invoice.paid');
});
