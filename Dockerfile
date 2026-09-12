# syntax=docker/dockerfile:1

# ---------------------------------------------------------------- assets
# Built in its own stage so node_modules never reaches the runtime image.
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js jsconfig.json ./
RUN npm run build

# ---------------------------------------------------------------- vendor
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
# --no-scripts: artisan is not here yet, and package discovery runs later.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---------------------------------------------------------------- runtime
FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor icu-dev oniguruma-dev libzip-dev postgresql-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql bcmath intl zip opcache \
    && rm -rf /var/cache/apk/*

# bcmath is not optional: the money layer uses it so rupees convert to paise
# exactly, and (int) (19.99 * 100) is 1998.

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
