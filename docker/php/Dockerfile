FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    git \
    unzip \
    zip \
    libpq \
    postgresql-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    icu-dev \
    zlib-dev \
    libpng-dev \
    g++ \
    make \
    autoconf \
    linux-headers \
    netcat-openbsd \
    && docker-php-ext-install pdo_pgsql pgsql opcache zip \
    && pecl install redis \
    && docker-php-ext-enable redis opcache \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/cache/apk/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
