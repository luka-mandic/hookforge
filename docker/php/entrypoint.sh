#!/bin/sh
set -e

# Only the service that sets RUN_MIGRATIONS=true migrates, so scaled workers never race on the schema.
if [ -n "$DB_HOST" ]; then
    echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."
    until nc -z "$DB_HOST" "${DB_PORT:-5432}"; do
        sleep 1
    done
fi

# A fresh named volume mounted on storage/ starts empty.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

# Development image only: composer is not shipped in the production image.
if [ ! -d vendor ] && command -v composer >/dev/null 2>&1; then
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
