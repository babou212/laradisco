# =============================================================================
# Multi-stage Dockerfile for Laradisco
# Stage 1: Install PHP dependencies (Composer)
# Stage 2: Build frontend assets (Node.js + PHP)
# Stage 3: Production image (FrankenPHP + Laravel Octane)
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
# Stage 2: Build frontend assets
# ---------------------------------------------------------------------------
FROM php:8.5-alpine AS frontend

WORKDIR /app

# Install Node.js and NPM
RUN apk add --no-cache nodejs npm

COPY package.json package-lock.json* ./
RUN npm ci

# Copy application files (needed for artisan)
COPY . .

# Setup environment for build
RUN cp .env.example .env && \
    sed -i 's/DB_CONNECTION=pgsql/DB_CONNECTION=sqlite/' .env && \
    sed -i 's/DB_DATABASE=laradisco/DB_DATABASE=\/app\/database\/database.sqlite/' .env && \
    sed -i 's/SESSION_DRIVER=database/SESSION_DRIVER=array/' .env && \
    mkdir -p database && touch database/database.sqlite

# Copy vendor directory from composer stage
COPY --from=composer /app/vendor ./vendor

RUN php artisan key:generate
RUN php artisan wayfinder:generate --with-form -v

# Build assets (requires PHP for wayfinder)
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3: Production image
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.5-alpine

# Install system dependencies
RUN apk add --no-cache \
    libpq-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    curl \
    linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        gd \
        pcntl \
        bcmath \
        sockets \
        mbstring \
    && apk del --no-cache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Create required directories
RUN mkdir -p \
    /var/log/php \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

WORKDIR /var/www/html

# Copy PHP production config
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-production.ini

# Copy application code
COPY . .

# Copy built frontend assets from stage 2
COPY --from=frontend /app/public/build public/build

# Copy vendor from stage 1
COPY --from=composer /app/vendor vendor

# Run composer post-install scripts (package discovery, etc.)
RUN php artisan package:discover --ansi || true

# Optimize for production
RUN php artisan route:cache || true \
    && php artisan view:cache || true \
    && php artisan event:cache || true

# Set permissions
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache \
    public

# FrankenPHP environment
ENV SERVER_NAME=":80"
ENV OCTANE_SERVER=frankenphp

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost/up || exit 1

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=80", "--admin-port=2019"]
