# syntax=docker/dockerfile:1

# ---- Frontend assets -------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- Composer dependencies (production only) -------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- PHP runtime shared by every PHP-based service -------------------------
FROM php:8.4-fpm-alpine AS base
WORKDIR /var/www/html

RUN apk add --no-cache libpq libzip netcat-openbsd \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev linux-headers \
    && docker-php-ext-install pdo_pgsql pgsql opcache zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]

# ---- Development: code is bind-mounted, composer available -----------------
FROM base AS development
RUN apk add --no-cache bash git unzip
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# ---- Production: code baked in, runs as an unprivileged user ---------------
FROM base AS production
COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data

# ---- Nginx serving the static files of the application ---------------------
FROM nginx:1.27-alpine AS nginx
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /app/public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
