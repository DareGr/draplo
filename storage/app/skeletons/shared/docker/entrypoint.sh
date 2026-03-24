#!/bin/sh
set -e
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link 2>/dev/null || true
exec "$@"
