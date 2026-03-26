# =============================================================================
# Multi-stage Dockerfile for Laradisco
# Stage 1: Install PHP dependencies (Composer)
# Stage 2: Production image (serversideup/php FrankenPHP + Laravel Octane)
# =============================================================================

# ---------------------------------------------------------------------------
# Stage 1: Install PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Stage 2: Production image
# ---------------------------------------------------------------------------
FROM serversideup/php:8.5-frankenphp

# Switch to root to install additional extensions & configure
USER root

# Install additional PHP extensions not included by default
# Pre-installed: opcache, pcntl, pdo_mysql, pdo_pgsql, redis, zip, mbstring
RUN install-php-extensions intl gd bcmath sockets pgsql

# Create required directories
RUN mkdir -p \
    /var/log/php \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

WORKDIR /var/www/html

# Copy PHP production config (loads after serversideup defaults)
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-production.ini

# Copy application code
COPY --chown=www-data:www-data . .

# Copy vendor from composer stage
COPY --from=composer --chown=www-data:www-data /app/vendor vendor

# Set permissions on directories that need to be writable at runtime
RUN chown -R www-data:www-data \
    /var/log/php \
    storage \
    bootstrap/cache \
    public

# Switch back to unprivileged user
USER www-data

# Run composer post-install scripts (package discovery, etc.)
RUN php artisan package:discover --ansi || true

# Generate wayfinder routes
RUN cp .env.example .env && \
    sed -i 's/DB_CONNECTION=pgsql/DB_CONNECTION=sqlite/' .env && \
    sed -i 's/DB_DATABASE=laradisco/DB_DATABASE=\/app\/database\/database.sqlite/' .env && \
    sed -i 's/SESSION_DRIVER=database/SESSION_DRIVER=array/' .env && \
    mkdir -p database && touch database/database.sqlite && \
    php artisan key:generate && \
    php artisan wayfinder:generate --with-form -v && \
    rm -f .env database/database.sqlite

# Optimize for production
RUN php artisan config:cache || true \
    && php artisan route:cache || true \
    && php artisan view:cache || true \
    && php artisan event:cache || true

# Environment configuration
ENV AUTORUN_ENABLED="true"
ENV PHP_OPCACHE_ENABLE="1"
ENV OCTANE_SERVER="frankenphp"
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

EXPOSE 8080 8443

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD ["healthcheck-octane"]

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--port=8080", "--max-requests=500"]
