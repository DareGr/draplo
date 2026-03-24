# --- Build frontend ---
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# --- Install PHP dependencies ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-autoloader --no-scripts --prefer-dist --ignore-platform-req=ext-pcntl
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Production image ---
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor postgresql-dev icu-dev linux-headers \
    && docker-php-ext-install pdo_pgsql intl pcntl opcache \
    && apk del linux-headers && rm -rf /var/cache/apk/*

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build-deps

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-app.ini"

RUN mkdir -p /var/run/nginx /var/log/supervisor

WORKDIR /var/www
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN rm -rf node_modules .git tests \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s CMD wget -qO- http://localhost/up || exit 1
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
