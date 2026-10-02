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

## Notes

This phase establishes the project foundation and local infrastructure only. Business functionality and webhook delivery logic will be added in later roadmap phases.
