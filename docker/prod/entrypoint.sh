#!/bin/sh
set -e
cd /var/www/html

# ---- Writable dirs ----------------------------------------------------------
mkdir -p \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ---- Run database migrations (compose depends_on already waited for db) -----
php artisan migrate --force --no-interaction

# ---- Build production caches -----------------------------------------------
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

exec "$@"
