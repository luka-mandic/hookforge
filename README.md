# Hookforge

Hookforge is a webhook delivery platform built with Laravel 13, PostgreSQL, RabbitMQ and Redis. Applications publish events to a project; Hookforge fans them out to subscribed HTTP endpoints, signs each request, retries failures with exponential backoff and records every attempt.

> **Status:** a portfolio and learning project. It is **not production-ready**; see [Known limitations](#known-limitations).

## Why this project exists

Reliable webhook delivery is a classic backend problem: at-least-once delivery, retries, idempotency, signing, back-pressure and observability. Hookforge is a compact, fully tested implementation of those concerns, built to show how I design asynchronous systems: explicit boundaries, transactional publishing, swappable policies and strict static analysis.

## Architecture

```
                 +-----------+      +-------------------+
 client/browser->|   nginx   |----->| php-fpm (Laravel) |---> PostgreSQL
                 +-----------+      +---------+---------+        ^
                                              | publish          |
                                              v (after commit)   |
                                   +----------------------+      |
                                   | RabbitMQ             |      |
                                   | hookforge.events     |      |
                                   | (topic) -> queue     |      |
                                   | hookforge.delivery   |      |
                                   +----------+-----------+      |
                                              | consume          |
                                              v                  |
                                   +----------------------+      |
                                   | worker x N           |------+
                                   | webhooks:work        |
                                   +----------+-----------+
                                              | signed HTTP POST
                                              v
                                      customer endpoints

 Redis: cache and API rate limiting
```

## Technology stack

- PHP 8.4, Laravel 13, Blade + Vite/Tailwind dashboard
- PostgreSQL 16 (SQLite for fast unit/feature tests)
- RabbitMQ (php-amqplib), Redis (phpredis)
- Docker Compose, Nginx, PHP-FPM
- Pest, PHPStan (level max, Larastan), Laravel Pint

## Main features

Implemented:

- Projects, webhook endpoints (per-endpoint secret, enable/disable) and event-type subscriptions
- Event ingestion API with validation, idempotency keys and Redis-backed rate limiting
- Transactional publishing: messages are sent to RabbitMQ only after the DB transaction commits
- Scalable consumer workers (`docker compose up --scale worker=3`) with prefetch 1 and graceful shutdown
- HMAC-SHA256 signed deliveries
- Retry policy with exponential backoff and permanent/transient failure classification
- Per-attempt history (status, HTTP code, response body, error)
- Dashboards: project overview, filtered/paginated delivery list, delivery detail
- Simulated test endpoints (2xx/4xx/5xx/timeout) for demonstrating retries
- Structured logging with no secrets or payloads ([docs/logging-strategy.md](docs/logging-strategy.md))
- Health checks for every container

## Event lifecycle

1. A client `POST`s an event to `/api/v1/projects/{project}/events`.
2. The request is validated, rate limited and checked for idempotency.
3. In one DB transaction the event and one `queued` delivery per matching active endpoint are stored.
4. After commit, a message per delivery is published to `hookforge.events`.
5. A worker consumes the message, loads the delivery, signs the payload and sends the HTTP request (10 s timeout).
6. A `DeliveryAttempt` is recorded. On 2xx the delivery becomes `delivered`.
7. On failure the retry policy decides: schedule another attempt (`next_attempt_at`), mark `failed` (permanent) or `dead_lettered` (attempts exhausted).
8. The message is acked; consumers nack without requeue on unexpected errors.

## RabbitMQ topology

- Exchange: `hookforge.events` (topic)
- Queue: `hookforge.delivery`, bound with `hookforge.delivery.#`
- Routing key: `hookforge.delivery.<event_type>`
- Message: identifiers only (`project_id`, `event_id`, `delivery_id`, `event_type`); never secrets
- Consumers use manual acks and `basic_qos(0, 1)` so work spreads evenly across workers

## Database model

```
users 1--* projects 1--* webhook_endpoints
                    1--* webhook_events (unique project_id + idempotency_key)
webhook_endpoints *--* event types via webhook_subscriptions
webhook_events    1--* webhook_deliveries *--1 webhook_endpoints
webhook_deliveries 1--* delivery_attempts (unique delivery_id + attempt_number)
```

All primary keys are ULIDs.

## Retry strategy

Defined in `DefaultRetryPolicy` behind a `RetryPolicy` interface.

- Max 5 attempts; delay = 1 s x 2^(attempt-1), capped at 30 s
- Retried: network errors, timeouts, 429, 500, 502, 503, 504
- Not retried: 400, 401, 403, 404, 405, 410, 422
- Exhausted attempts end in `dead_lettered`

## Idempotency strategy

- Publishers may send an `idempotency_key`; a repeat for the same project returns the existing event instead of creating a new one.
- Workers skip deliveries that are already `delivered`, so a duplicated broker message yields one HTTP request.
- Delivery is **at-least-once**: receivers should dedupe using the `X-Hookforge-Delivery` header.

## Security considerations

- Every delivery carries `X-Hookforge-Signature: sha256=<hmac of the raw body>` using the endpoint secret.
- The signed raw body is exactly what is sent, so verification is byte-exact.
- Project and endpoint access is enforced by policies (users only see their own data).
- Secrets and payloads are never logged; queue messages contain no secrets.
- Compose reads secrets from an env file; `.env*` files are gitignored and only `CHANGE_ME` templates are committed.
- Event publishing is rate limited per user and project (HTTP 429 with `Retry-After`).

## Testing strategy

- Unit tests: signer, retry policy, request builder, filters
- Feature tests: dashboards, endpoint CRUD, event API, delivery flow, rate limiting, simulator
- Integration tests (`tests/Integration`): real Redis, RabbitMQ and PostgreSQL; each skips if its service is unreachable
- 177 tests, run on the host and inside the container

## Docker setup

- `docker-compose.yml`: production-shaped stack (nginx, php-fpm, worker, postgres, redis, rabbitmq), named volumes, health checks, `depends_on: service_healthy`, no bind mounts
- `docker-compose.override.yml`: merged automatically for local development (bind mounts, published ports, RabbitMQ management UI)
- `docker/Dockerfile`: multi-stage with `development`, `production` and `nginx` targets
- Only `php-fpm` runs migrations (`RUN_MIGRATIONS=true`)

## Running the application

```bash
cp .env.example .env
docker compose up -d --build --scale worker=3
docker compose exec php-fpm php artisan key:generate   # if APP_KEY is empty
```

- App: http://localhost:8080 (health: `/up`, `/health`)
- RabbitMQ UI: http://localhost:15672 (guest/guest in local development)
- PostgreSQL: localhost:5432 (hookforge/hookforge)

Useful commands:

```bash
docker compose ps
docker compose logs -f worker
docker compose down
```

For a production-style run:

```bash
cp .env.production.example .env.production   # replace every CHANGE_ME
ENV_FILE=.env.production docker compose --env-file .env.production -f docker-compose.yml up -d --build --scale worker=3
```

## Running tests

```bash
./vendor/bin/pest                          # unit + feature
./vendor/bin/pest --testsuite=Integration  # needs Redis, RabbitMQ, PostgreSQL
```

Inside the containers (the container `.env` overrides phpunit, so set the environment explicitly):

```bash
docker compose exec -T -e APP_ENV=testing \
  -e INTEGRATION_REDIS_HOST=redis -e INTEGRATION_RABBITMQ_HOST=rabbitmq \
  -e INTEGRATION_PG_HOST=postgres -e INTEGRATION_PG_DATABASE=hookforge_test \
  -e INTEGRATION_PG_USERNAME=hookforge -e INTEGRATION_PG_PASSWORD=hookforge \
  php-fpm ./vendor/bin/pest
```

The PostgreSQL integration test runs `migrate:fresh`, so its database name must end in `_test`. Other overrides: `INTEGRATION_*_PORT`, `INTEGRATION_RABBITMQ_USER`/`PASSWORD`, `INTEGRATION_PG_PORT`.

## Static analysis

```bash
./vendor/bin/phpstan analyse --memory-limit=1G   # level max
./vendor/bin/pint --test                         # code style
```

## Example API requests

The API uses the application's session authentication (log in through the web UI first); there are no API tokens yet. Rate limit settings: `RATE_LIMIT_EVENTS_PER_MINUTE` (default 60), `RATE_LIMIT_EVENTS_DECAY_SECONDS`, `CACHE_LIMITER_STORE`.

```bash
curl -X POST http://localhost:8080/api/v1/projects/$PROJECT_ID/events \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "X-XSRF-TOKEN: $XSRF" -b cookies.txt \
  -d '{
        "type": "order.created",
        "data": {"order_id": "ord_123", "amount": 1299},
        "idempotency_key": "order-123-created"
      }'
```

A throttled request returns `429` with `Retry-After`, `X-RateLimit-Limit` and `X-RateLimit-Remaining`.

## Example webhook signature verification

Each delivery includes `X-Hookforge-Event`, `X-Hookforge-Delivery` and `X-Hookforge-Signature`. Verify against the **raw** request body:

```php
$raw = file_get_contents('php://input');
$expected = 'sha256='.hash_hmac('sha256', $raw, $endpointSecret);

if (! hash_equals($expected, $_SERVER['HTTP_X_HOOKFORGE_SIGNATURE'] ?? '')) {
    http_response_code(401);
    exit;
}
```

```python
import hmac, hashlib

def verify(raw: bytes, header: str, secret: str) -> bool:
    expected = "sha256=" + hmac.new(secret.encode(), raw, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, header)
```

## Known limitations

- Not production-ready; no load testing or security audit
- API authentication is session-based only; no API keys or tokens
- Retries are recorded (`queued` + `next_attempt_at`) but nothing republishes them to RabbitMQ yet, so scheduled retries need a scheduler or delayed-message mechanism
- Idempotency is check-then-insert; concurrent duplicates fall back to the database unique constraint
- Delivery URLs are not restricted, so there is no SSRF protection against internal addresses
- No signature timestamp, so replay protection is left to receivers
- Single queue, no dead-letter exchange; `dead_lettered` is a database status only
- Default local credentials (`guest`, `hookforge`) are for development only

## Possible future improvements

Not implemented:

- Republish scheduled retries via delayed exchange or scheduler
- API tokens with scopes
- SSRF protection (block private ranges, DNS pinning)
- Timestamped signatures for replay protection
- RabbitMQ dead-letter exchange and manual replay of dead-lettered deliveries
- Per-endpoint rate limiting and circuit breaking
- Metrics (Prometheus) and tracing
- CI pipeline running tests, PHPStan and Pint
