# =============================================================================
# Multi-stage Dockerfile for Laradisco
# Stage 1: Build frontend assets (Node.js)
# Stage 2: Install PHP dependencies (Composer)
# Stage 3: Production image (Nginx + PHP-FPM via supervisord)
# =============================================================================

# ---------------------------------------------------------------------------
# Stage 1: Build frontend assets
# ---------------------------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY resources/ resources/
COPY vite.config.ts tsconfig.json components.json tailwind.config.* ./
COPY public/ public/

RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2: Install PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Stage 3: Production image
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    nginx-mod-http-headers-more \
    supervisor \
    libpq-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        mbstring \
        opcache \
        gd \
        pcntl \
        bcmath \
        sockets \
    && apk del --no-cache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Create required directories
RUN mkdir -p \
    /var/log/php \
    /var/log/supervisor \
    /var/log/nginx \
    /run/nginx \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

WORKDIR /var/www/html

# Copy PHP production config
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-production.ini

# Copy Nginx config
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application code
COPY . .

# Copy built frontend assets from stage 1
COPY --from=frontend /app/public/build public/build

# Copy vendor from stage 2
COPY --from=composer /app/vendor vendor

# Run composer post-install scripts (package discovery, etc.)
RUN php artisan package:discover --ansi || true

# Optimize for production
RUN php artisan config:cache || true \
    && php artisan route:cache || true \
    && php artisan view:cache || true \
    && php artisan event:cache || true

# Set permissions
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache \
    public

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/healthz || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
