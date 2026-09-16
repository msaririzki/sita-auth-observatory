FROM composer:2 AS vendor

WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        icu-data-full \
        icu-dev \
        icu-libs \
        libzip \
        libzip-dev \
        sqlite-libs \
        sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_sqlite \
        zip \
    && apk del icu-dev libzip-dev sqlite-dev

WORKDIR /var/www/html

FROM runtime AS frontend

RUN apk add --no-cache nodejs npm

COPY . .
COPY --from=vendor /build/vendor ./vendor
RUN php artisan package:discover --ansi \
    && npm ci \
    && npm run build

FROM runtime AS application

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /build/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /var/www/html/public/build ./public/build
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/observatory-entrypoint

RUN mkdir -p \
        storage/app/data \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["observatory-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.29-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=application /var/www/html/public /var/www/html/public
