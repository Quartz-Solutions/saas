# ============================================================================
# Development image
# ----------------------------------------------------------------------------
# Runs PHP-FPM + nginx + Vite dev server in a single container. Source is
# bind-mounted at runtime; vendor/, node_modules/, bootstrap/cache/,
# storage/framework/, storage/logs/ are anonymous volumes seeded from this
# image so host and container never collide on cache state.
# ============================================================================

FROM php:8.4-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    NPM_CONFIG_UPDATE_NOTIFIER=false \
    NPM_CONFIG_FUND=false \
    PNPM_HOME=/usr/local/bin

# ---- System packages + PHP extensions --------------------------------------
RUN apk add --no-cache \
        nginx supervisor curl bash zip unzip git \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        libzip-dev icu-dev oniguruma-dev sqlite-dev libxml2-dev \
        postgresql-dev postgresql-client \
        linux-headers nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pdo_sqlite mbstring xml gd zip intl bcmath opcache pcntl

# ---- Redis PHP extension (isolated build deps) -----------------------------
# $PHPIZE_DEPS is a whitespace-separated list — do NOT quote it.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# ---- Composer --------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- pnpm ------------------------------------------------------------------
RUN npm install -g pnpm@10

WORKDIR /var/www/html

# ---- Seed vendor/ and node_modules/ (preserved by anonymous volumes) -------
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist

COPY package.json pnpm-lock.yaml* pnpm-workspace.yaml* .npmrc* ./
RUN pnpm install --prefer-frozen-lockfile

# ---- Runtime configuration -------------------------------------------------
COPY docker/dev/nginx.conf       /etc/nginx/http.d/default.conf
COPY docker/dev/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/dev/php.ini          /usr/local/etc/php/conf.d/custom.ini
COPY docker/dev/entrypoint.sh    /entrypoint.sh
RUN chmod +x /entrypoint.sh

# ---- Directories nginx/php-fpm need at boot --------------------------------
RUN mkdir -p /run/nginx \
             /var/www/html/storage/logs \
             /var/www/html/storage/framework/cache \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/bootstrap/cache

HEALTHCHECK --interval=30s --timeout=5s --start-period=45s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

EXPOSE 80 5173

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
