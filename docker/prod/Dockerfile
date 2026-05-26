# ============================================================================
# POS — Production image
# ----------------------------------------------------------------------------
# Single-stage with post-build cleanup: composer --no-dev, npm run build,
# then node + build toolchain are removed so the runtime image only ships
# PHP-FPM + nginx + supervisor + baked assets + optimised vendor.
# ============================================================================

FROM php:8.4-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    NPM_CONFIG_UPDATE_NOTIFIER=false \
    NPM_CONFIG_FUND=false \
    PNPM_HOME=/usr/local/bin

# ---- System packages + PHP extensions --------------------------------------
RUN apk add --no-cache \
        nginx supervisor curl \
        libpng libjpeg-turbo freetype libzip icu oniguruma libxml2 \
        libpq postgresql-client \
    && apk add --no-cache --virtual .build-deps \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev \
        icu-dev oniguruma-dev libxml2-dev postgresql-dev \
        linux-headers nodejs npm git zip unzip $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql mbstring xml gd zip intl bcmath opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- pnpm ------------------------------------------------------------------
RUN npm install -g pnpm@10

WORKDIR /var/www/html

# ---- Install PHP deps (production only) ------------------------------------
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-scripts \
        --no-autoloader --prefer-dist

# ---- Install and build JS assets -------------------------------------------
COPY package.json pnpm-lock.yaml* pnpm-workspace.yaml* .npmrc* ./
RUN pnpm install --prefer-frozen-lockfile

# ---- App source + final autoloader + asset build ---------------------------
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && pnpm run build

# ---- Strip build toolchain (keep only runtime libs) ------------------------
RUN rm -rf node_modules /tmp/composer /root/.composer \
 && apk del .build-deps

# ---- Runtime configuration -------------------------------------------------
COPY docker/prod/nginx.conf       /etc/nginx/http.d/default.conf
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/prod/php.ini          /usr/local/etc/php/conf.d/custom.ini
COPY docker/prod/entrypoint.sh    /entrypoint.sh
RUN chmod +x /entrypoint.sh

# ---- Runtime dirs + permissions (bind-mount-free, so these persist) --------
RUN mkdir -p /run/nginx \
             /var/www/html/storage/logs \
             /var/www/html/storage/framework/cache \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/bootstrap/cache \
 && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
 && chmod -R 0775 /var/www/html/storage /var/www/html/bootstrap/cache

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
