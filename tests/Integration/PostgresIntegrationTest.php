<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Requires a reachable PostgreSQL database whose name ends in "_test" because the
 * schema is dropped and rebuilt. Configure with INTEGRATION_PG_HOST, INTEGRATION_PG_PORT,
 * INTEGRATION_PG_DATABASE, INTEGRATION_PG_USERNAME and INTEGRATION_PG_PASSWORD.
 */
beforeEach(function () {
    $host = env('INTEGRATION_PG_HOST', '127.0.0.1');
    $port = (int) env('INTEGRATION_PG_PORT', 5432);
    $database = (string) env('INTEGRATION_PG_DATABASE', 'hookforge_test');

    if (! extension_loaded('pdo_pgsql')) {
        $this->markTestSkipped('The pdo_pgsql extension is not installed.');
    }

    $reachable = @fsockopen($host, $port, $errno, $errstr, 0.5);

    if ($reachable === false) {
        $this->markTestSkipped("PostgreSQL is not reachable at {$host}:{$port}.");
    }

    fclose($reachable);

    if (! str_ends_with($database, '_test')) {
        $this->markTestSkipped('The integration database name must end with "_test".');
    }

    config([
        'database.connections.pgsql_integration' => [
            ...config('database.connections.pgsql'),
            'url' => null,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => env('INTEGRATION_PG_USERNAME', 'hookforge'),
            'password' => env('INTEGRATION_PG_PASSWORD', 'hookforge'),
        ],
        'database.default' => 'pgsql_integration',
    ]);

    DB::purge('pgsql_integration');

    try {
        Artisan::call('migrate:fresh', ['--database' => 'pgsql_integration', '--force' => true]);
    } catch (Throwable $exception) {
        $this->markTestSkipped('PostgreSQL integration database is not usable: '.$exception->getMessage());
    }
});

afterEach(function () {
    DB::purge('pgsql_integration');
});

function pgGraph(): array
{
    $project = Project::factory()->for(User::factory())->create();
    $endpoint = WebhookEndpoint::factory()->for($project)->create();
    $event = WebhookEvent::factory()->for($project)->create(['payload' => ['a' => 1]]);

    return [$project, $endpoint, $event];
}

test('the application is running against postgresql', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

test('migrations create the expected tables', function () {
    foreach (['projects', 'webhook_endpoints', 'webhook_events', 'webhook_deliveries', 'delivery_attempts'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table {$table}");
    }
});

test('models use ulid primary keys', function () {
    [$project] = pgGraph();

    expect((string) $project->getKey())->toHaveLength(26);
});

test('the idempotency key is unique per project at the database level', function () {
    [$project] = pgGraph();

    $project->webhookEvents()->create(['event_type' => 'a.b', 'payload' => ['x' => 1], 'status' => 'queued', 'idempotency_key' => 'dup']);

    $project->webhookEvents()->create(['event_type' => 'a.b', 'payload' => ['x' => 1], 'status' => 'queued', 'idempotency_key' => 'dup']);
})->throws(UniqueConstraintViolationException::class);

test('the same idempotency key is allowed in another project', function () {
    [$projectA] = pgGraph();
    [$projectB] = pgGraph();

    $projectA->webhookEvents()->create(['event_type' => 'a.b', 'payload' => ['x' => 1], 'status' => 'queued', 'idempotency_key' => 'same']);
    $projectB->webhookEvents()->create(['event_type' => 'a.b', 'payload' => ['x' => 1], 'status' => 'queued', 'idempotency_key' => 'same']);

    expect(WebhookEvent::query()->where('idempotency_key', 'same')->count())->toBe(2);
});

test('foreign keys reject deliveries that reference missing rows', function () {
    [$project] = pgGraph();

    WebhookDelivery::query()->create([
        'project_id' => $project->getKey(),
        'endpoint_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'event_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
        'status' => 'queued',
        'attempt_count' => 0,
    ]);
})->throws(QueryException::class);

test('row locking used by delivery claiming works on postgresql', function () {
    [$project, $endpoint, $event] = pgGraph();
    $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event, 'event')->create(['status' => 'queued']);

    $locked = DB::transaction(fn () => WebhookDelivery::query()->whereKey($delivery->getKey())->lockForUpdate()->first());

    expect($locked?->getKey())->toBe($delivery->getKey());
});
