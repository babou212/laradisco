#!/bin/bash
# Forge Deployment Script for Laradisco
# Configure this in Forge: Sites > your-site > Deployments > Deploy Script

cd /home/forge/{{DOMAIN}}

# Pull latest code
git pull origin $FORGE_SITE_BRANCH

# Install dependencies (production-optimised)
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Run database migrations and seed (idempotent)
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan db:seed --class=ProductionSeeder --force

# Cache configuration, routes, and events
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan event:cache

# Reload Octane workers gracefully (zero-downtime, non-fatal if not yet running)
$FORGE_PHP artisan octane:reload || true

# Restart Horizon (daemon will auto-restart it)
$FORGE_PHP artisan horizon:terminate || true

# Restart Reverb WebSocket server
$FORGE_PHP artisan reverb:restart || true
