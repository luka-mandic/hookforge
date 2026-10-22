<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Testing\TestResponse;

function deliveryEventTypes(TestResponse $response): array
{
    return $response->viewData('deliveries')->getCollection()
        ->map(fn (WebhookDelivery $delivery) => $delivery->event->event_type)
        ->sort()->values()->all();
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();

    $this->make = function (string $type, string $status, ?WebhookEndpoint $endpoint = null) {
        $endpoint ??= WebhookEndpoint::factory()->for($this->project)->create(['name' => "Hook {$type}"]);
        $event = WebhookEvent::factory()->for($this->project)->create(['event_type' => $type, 'status' => 'queued']);

        return WebhookDelivery::factory()->for($this->project)->for($endpoint)->for($event, 'event')
            ->create(['status' => $status]);
    };

    ($this->make)('invoice.paid', 'delivered');
    ($this->make)('invoice.failed', 'dead_lettered');
    ($this->make)('customer.created', 'queued');
});

test('project deliveries can be filtered by status', function () {
    $response = $this->actingAs($this->user)
        ->get(route('projects.deliveries.index', [$this->project, 'status' => 'dead_lettered']));

    expect(deliveryEventTypes($response))->toBe(['invoice.failed']);
});

test('project deliveries can be filtered by event type', function () {
    $response = $this->actingAs($this->user)
        ->get(route('projects.deliveries.index', [$this->project, 'event_type' => 'customer.created']));

    expect(deliveryEventTypes($response))->toBe(['customer.created']);
});

test('project deliveries can be filtered by endpoint', function () {
    $other = WebhookEndpoint::factory()->for($this->project)->create(['name' => 'Other Hook']);
    ($this->make)('only.other', 'delivered', $other);

    $response = $this->actingAs($this->user)
        ->get(route('projects.deliveries.index', [$this->project, 'endpoint' => $other->id]));

    expect(deliveryEventTypes($response))->toBe(['only.other']);
});

test('project deliveries can be filtered by date range', function () {
    ($this->make)('ancient.event', 'delivered')->forceFill(['created_at' => now()->subMonths(2)])->save();

    $recent = $this->actingAs($this->user)
        ->get(route('projects.deliveries.index', [$this->project, 'date_from' => now()->subDay()->toDateString()]));
    $old = $this->actingAs($this->user)
        ->get(route('projects.deliveries.index', [$this->project, 'date_to' => now()->subMonth()->toDateString()]));

    expect(deliveryEventTypes($recent))->not->toContain('ancient.event')->toContain('invoice.paid')
        ->and(deliveryEventTypes($old))->toBe(['ancient.event']);
});

test('project deliveries can be searched by endpoint name or event type', function () {
    $byType = $this->actingAs($this->user)
        ->get(route('projects.deliveries.index', [$this->project, 'search' => 'customer']));
    $byEndpoint = $this->actingAs($this->user)
        ->get(route('projects.deliveries.index', [$this->project, 'search' => 'Hook invoice.f']));

    expect(deliveryEventTypes($byType))->toBe(['customer.created'])
        ->and(deliveryEventTypes($byEndpoint))->toBe(['invoice.failed']);
});

test('filters that match nothing render an empty result without error', function () {
    $response = $this->actingAs($this->user)
        ->get(route('projects.deliveries.index', [$this->project, 'status' => 'no-such-status']))
        ->assertOk();

    expect(deliveryEventTypes($response))->toBe([]);
});

test('combined filters narrow results together', function () {
    $response = $this->actingAs($this->user)
        ->get(route('projects.deliveries.index', [$this->project, 'status' => 'delivered', 'event_type' => 'invoice.failed']));

    expect(deliveryEventTypes($response))->toBe([]);
});

test('project events can be filtered by status and searched', function () {
    WebhookEvent::factory()->for($this->project)->create(['event_type' => 'special.event', 'status' => 'processed']);

    $byStatus = $this->actingAs($this->user)
        ->get(route('projects.events.index', [$this->project, 'status' => 'processed']));
    $bySearch = $this->actingAs($this->user)
        ->get(route('projects.events.index', [$this->project, 'search' => 'invoice.paid']));

    expect($byStatus->viewData('events')->pluck('event_type')->all())->toBe(['special.event'])
        ->and($bySearch->viewData('events')->pluck('event_type')->all())->toBe(['invoice.paid']);
});

test('the global dashboard ignores a project filter that belongs to someone else', function () {
    $foreign = Project::factory()->for(User::factory())->create();
    $foreignEvent = WebhookEvent::factory()->for($foreign)->create(['event_type' => 'foreign.event']);
    WebhookDelivery::factory()->for($foreign)
        ->for(WebhookEndpoint::factory()->for($foreign)->create(), 'endpoint')
        ->for($foreignEvent, 'event')->create(['status' => 'delivered']);

    $response = $this->actingAs($this->user)->get(route('deliveries.index', ['project' => $foreign->id]));

    expect(deliveryEventTypes($response))->not->toContain('foreign.event');
});
