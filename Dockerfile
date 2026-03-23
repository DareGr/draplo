# =============================================================================
# Stage 1: Build frontend assets
# =============================================================================
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources/ ./resources/

RUN npm run build

# =============================================================================
# Stage 2: Install PHP dependencies
# =============================================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-autoloader \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-req=ext-pcntl

COPY . .

RUN composer dump-autoload --optimize --no-dev

# =============================================================================
# Stage 3: Production image
# =============================================================================
FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    icu-dev \
    linux-headers \
    && docker-php-ext-install \
    pdo_pgsql \
    intl \
    pcntl \
    opcache \
    && apk del linux-headers \
    && rm -rf /var/cache/apk/*

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-draplo.ini"
COPY docker/www.conf /usr/local/etc/php-fpm.d/zz-draplo.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Create required directories
RUN mkdir -p /var/run/nginx /var/log/supervisor

WORKDIR /var/www

# Copy application
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Copy built frontend assets
COPY --from=assets /app/public/build ./public/build

# Remove dev files from final image
RUN rm -rf node_modules tests .git .claude* docs \
    docker-compose.yml docker-compose.prod.yml \
    .env .env.example .env.production.example \
    phpunit.xml vite.config.js package.json package-lock.json

# Set permissions
RUN chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://localhost/up || exit 1

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
