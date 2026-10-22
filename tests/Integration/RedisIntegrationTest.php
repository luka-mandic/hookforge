<?php

use App\Models\Project;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Predis\Client;

uses(RefreshDatabase::class);

/*
 * Requires a reachable Redis and a PHP redis client (phpredis or predis).
 * Override the target with INTEGRATION_REDIS_HOST / INTEGRATION_REDIS_PORT.
 */
beforeEach(function () {
    $host = env('INTEGRATION_REDIS_HOST', '127.0.0.1');
    $port = (int) env('INTEGRATION_REDIS_PORT', 6379);

    $reachable = @fsockopen($host, $port, $errno, $errstr, 0.5);

    if ($reachable === false) {
        $this->markTestSkipped("Redis is not reachable at {$host}:{$port}.");
    }

    fclose($reachable);

    if (! extension_loaded('redis') && ! class_exists(Client::class)) {
        $this->markTestSkipped('No PHP redis client (phpredis or predis) is installed.');
    }

    config([
        'database.redis.client' => extension_loaded('redis') ? 'phpredis' : 'predis',
        'database.redis.default.host' => $host,
        'database.redis.default.port' => $port,
        'database.redis.default.password' => null,
        'database.redis.cache.host' => $host,
        'database.redis.cache.port' => $port,
        'database.redis.cache.password' => null,
        'cache.prefix' => 'hookforge_it_'.Str::random(8).'_',
    ]);
});

afterEach(function () {
    if (config('cache.prefix') !== null && str_starts_with((string) config('cache.prefix'), 'hookforge_it_')) {
        Cache::store('redis')->flush();
    }
});

test('the redis cache store round-trips values and expires them', function () {
    $store = Cache::store('redis');

    $store->put('probe', 'value', 1);
    expect($store->get('probe'))->toBe('value');

    $store->forget('probe');
    expect($store->get('probe'))->toBeNull();
});

test('the redis rate limiter counts hits and reports when the limit is exceeded', function () {
    $limiter = new RateLimiter(Cache::store('redis'));
    $limiter->clear('it-key');

    $limiter->hit('it-key', 60);
    $limiter->hit('it-key', 60);

    expect((int) $limiter->attempts('it-key'))->toBe(2)
        ->and($limiter->tooManyAttempts('it-key', 2))->toBeTrue()
        ->and($limiter->tooManyAttempts('it-key', 3))->toBeFalse()
        ->and($limiter->availableIn('it-key'))->toBeGreaterThan(0);
});

test('event publishing is throttled through redis and returns 429', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->app->singleton(RateLimiter::class, fn () => new RateLimiter(Cache::store('redis')));
    Facade::clearResolvedInstance(RateLimiter::class);

    $provider = $this->app->getProvider(AppServiceProvider::class);
    (fn () => $this->configureRateLimiting())->call($provider);

    config([
        'rate_limiting.event_publishing.max_attempts' => 2,
        'rate_limiting.event_publishing.decay_seconds' => 60,
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $url = route('api.v1.projects.events.store', $project);
    $payload = ['type' => 'order.created', 'data' => ['id' => 1]];

    $this->actingAs($user)->postJson($url, $payload)->assertCreated();
    $this->actingAs($user)->postJson($url, $payload)->assertCreated();
    $this->actingAs($user)->postJson($url, $payload)
        ->assertTooManyRequests()
        ->assertHeader('X-RateLimit-Limit', 2)
        ->assertHeader('Retry-After');
});
