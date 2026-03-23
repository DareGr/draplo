#!/bin/sh
set -e

# Cache Laravel config, routes, and views for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations automatically (safe with --force in production)
php artisan migrate --force

# Fix storage link if missing
php artisan storage:link 2>/dev/null || true

exec "$@"
