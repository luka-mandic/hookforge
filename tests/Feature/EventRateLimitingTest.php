<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config([
        'rate_limiting.event_publishing.max_attempts' => 2,
        'rate_limiting.event_publishing.decay_seconds' => 60,
    ]);
    RateLimiter::clear('');

    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

function publishEvent(object $test, User $user, Project $project, string $key = 'k'): TestResponse
{
    return $test->actingAs($user)->postJson(
        route('api.v1.projects.events.store', $project),
        ['type' => 'order.created', 'data' => ['order_id' => $key.random_int(1, 999999)]],
    );
}

test('requests within the limit include rate limit headers', function () {
    publishEvent($this, $this->user, $this->project)
        ->assertCreated()
        ->assertHeader('X-RateLimit-Limit', 2)
        ->assertHeader('X-RateLimit-Remaining', 1);

    publishEvent($this, $this->user, $this->project)
        ->assertCreated()
        ->assertHeader('X-RateLimit-Remaining', 0);
});

test('exceeding the limit returns 429 with retry headers', function () {
    publishEvent($this, $this->user, $this->project)->assertCreated();
    publishEvent($this, $this->user, $this->project)->assertCreated();

    $response = publishEvent($this, $this->user, $this->project)->assertTooManyRequests();

    expect($response->headers->get('Retry-After'))->not->toBeNull()
        ->and((int) $response->headers->get('X-RateLimit-Remaining'))->toBe(0)
        ->and($response->headers->get('X-RateLimit-Limit'))->toBe('2');
});

test('rejected requests do not create events', function () {
    publishEvent($this, $this->user, $this->project);
    publishEvent($this, $this->user, $this->project);
    publishEvent($this, $this->user, $this->project)->assertTooManyRequests();

    expect($this->project->webhookEvents()->count())->toBe(2);
});

test('limits are tracked per project', function () {
    $other = Project::factory()->for($this->user)->create();

    publishEvent($this, $this->user, $this->project);
    publishEvent($this, $this->user, $this->project);
    publishEvent($this, $this->user, $this->project)->assertTooManyRequests();

    publishEvent($this, $this->user, $other)->assertCreated();
});

test('another identity is not affected by an exhausted limit', function () {
    $otherUser = User::factory()->create();

    publishEvent($this, $this->user, $this->project);
    publishEvent($this, $this->user, $this->project);
    publishEvent($this, $this->user, $this->project)->assertTooManyRequests();

    $this->app['auth']->forgetGuards();
    publishEvent($this, $otherUser, $this->project)->assertForbidden()
        ->assertHeader('X-RateLimit-Remaining', 1);
});

test('limits are configurable', function () {
    config(['rate_limiting.event_publishing.max_attempts' => 1]);

    publishEvent($this, $this->user, $this->project)->assertCreated();
    publishEvent($this, $this->user, $this->project)->assertTooManyRequests();
});
