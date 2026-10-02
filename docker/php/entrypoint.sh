#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

echo "Waiting for PostgreSQL..."
until nc -z postgres 5432; do
    sleep 1
done

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist --no-progress --no-scripts
fi

php artisan key:generate --force
php artisan migrate --force

exec "$@"
