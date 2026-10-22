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

## Services

- Nginx: http://localhost:8080
- PostgreSQL: localhost:5432
- Redis: localhost:6379
- RabbitMQ management: http://localhost:15672

## Common commands

```bash
# View running containers
docker compose ps

# View logs
docker compose logs -f php-fpm

# Run Laravel commands inside the app container
docker compose exec php-fpm php artisan --version

# Stop the stack
docker compose down
```

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
