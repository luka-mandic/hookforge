# Hookforge

Hookforge is a Laravel-based webhook delivery platform built for local development with Docker.

## Requirements

- Docker
- Docker Compose

## Quick start

1. Copy the example environment file:

    ```bash
    cp .env.example .env
    ```

2. Start the application stack:

    ```bash
    docker compose up --build -d
    ```

3. The application will be available at http://localhost:8080.

4. Health check endpoint:

    ```bash
    curl http://localhost:8080/health
    ```

## Architecture

```
client -> nginx -> php-fpm (Laravel) -> PostgreSQL
                       |                  ^
                       v                  |
                   RabbitMQ -> worker(s) --+--> customer endpoints
                   Redis: cache and rate limiting
```

- `nginx` serves static assets and proxies PHP to `php-fpm`.
- `php-fpm` runs the API and dashboard; it is the only service that runs migrations (`RUN_MIGRATIONS=true`).
- `worker` runs `php artisan webhooks:work`, consumes `hookforge.delivery` with prefetch 1 and shuts down gracefully on SIGTERM.
- `postgres`, `redis` and `rabbitmq` use named volumes (`postgres_data`, `redis_data`, `rabbitmq_data`); `storage_data` holds Laravel storage.
- Every service has a health check and dependants wait with `service_healthy`.

## Compose files

- `docker-compose.yml` is the production-shaped stack: no bind mounts, no published database ports, secrets only from the env file.
- `docker-compose.override.yml` is merged automatically for local development: bind-mounts the code, publishes service ports and enables the RabbitMQ management UI.

## Local development services

- Nginx: http://localhost:8080
- PostgreSQL: localhost:5432
- Redis: localhost:6379
- RabbitMQ management: http://localhost:15672

## Common commands

```bash
# Start and scale workers
docker compose up -d --build --scale worker=3

# View running containers and logs
docker compose ps
docker compose logs -f worker

# Run Laravel commands inside the app container
docker compose exec php-fpm php artisan migrate

# Stop the stack
docker compose down
```

## Production deployment

```bash
cp .env.production.example .env.production   # replace every CHANGE_ME value
docker compose run --rm --no-deps php-fpm php artisan key:generate --show   # put it in APP_KEY
ENV_FILE=.env.production docker compose --env-file .env.production -f docker-compose.yml up -d --build --scale worker=3
```

Never commit `.env` or `.env.production`. Migrations run when `php-fpm` starts; set `RUN_MIGRATIONS=false` to disable.

## Default credentials

- PostgreSQL database: hookforge
- PostgreSQL user: hookforge
- PostgreSQL password: hookforge
- RabbitMQ user: guest
- RabbitMQ password: guest

## Logging strategy

Structured application logging is used for the webhook lifecycle so operators can trace event publication, retries, delivery attempts, and dead-letter transitions without exposing secrets or payload data.

See [docs/logging-strategy.md](docs/logging-strategy.md) for the full logging contract and examples.

## RabbitMQ topology

Hookforge publishes webhook events to RabbitMQ using a topic exchange and explicit routing keys.

- Exchange: `hookforge.events`
- Default queue: `hookforge.delivery`
- Routing key pattern: `hookforge.delivery.<event_type>`

Example payload:

```json
{
    "project_id": "01J...",
    "event_id": "01J...",
    "event_type": "order.created",
    "payload": {
        "order_id": "ord_123",
        "amount": 1299
    }
}
```

The AMQP configuration is driven by environment variables in `.env`, and publishing is deferred until the related database transaction is committed.

## API rate limiting

Event publishing (`POST /api/v1/projects/{project}/events`) is rate limited per authenticated user and project using Redis. Exceeding the limit returns HTTP 429 with `Retry-After`, `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers.

Configure via `RATE_LIMIT_EVENTS_PER_MINUTE` (default 60), `RATE_LIMIT_EVENTS_DECAY_SECONDS` (default 60) and `CACHE_LIMITER_STORE` (default `redis`).

## Testing

Run the unit and feature suites with `./vendor/bin/pest`.

Integration tests live in `tests/Integration` and run against real Redis, RabbitMQ and PostgreSQL. Each test skips itself when its service is unreachable. Run them with `./vendor/bin/pest --testsuite=Integration`.

Connection settings can be overridden with `INTEGRATION_REDIS_HOST`/`PORT`, `INTEGRATION_RABBITMQ_HOST`/`PORT`/`USER`/`PASSWORD` and `INTEGRATION_PG_HOST`/`PORT`/`DATABASE`/`USERNAME`/`PASSWORD`. The PostgreSQL test runs `migrate:fresh`, so the database name must end in `_test`. The Redis test needs the phpredis extension or predis.
