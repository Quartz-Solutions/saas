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

# ---- Application key --------------------------------------------------------
# APP_KEY normally comes from .env.production (env_file). If it's missing or
# malformed, generate one so boot doesn't 500 on every request. --show prints
# the key without writing a file (no .env is baked into the image); exporting
# it makes the value available to config:cache and the exec'd supervisord/
# php-fpm below. Set a real APP_KEY in .env.production to persist it across
# restarts and share it with the queue/scheduler containers.
if [ -z "$APP_KEY" ] || ! printf '%s' "$APP_KEY" | grep -qE '^base64:.+'; then
    echo "entrypoint: APP_KEY missing or invalid — generating an ephemeral key"
    APP_KEY="$(php artisan key:generate --show)"
    export APP_KEY
fi

# ---- Public storage symlink (public/storage -> storage/app/public) ----------
php artisan storage:link

# ---- Run database migrations (compose depends_on already waited for db) -----
php artisan migrate --force --no-interaction

# ---- Build production caches -----------------------------------------------
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

exec "$@"
