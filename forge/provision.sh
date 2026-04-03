#!/bin/bash
# Forge Server Provisioning Recipe for Laradisco
# Run this as a Forge Recipe after initial server provisioning.
# This installs LiveKit and configures firewall rules.
#
# In Forge: Recipes > New Recipe > paste this > run on your server.

set -e

echo "==> Installing LiveKit Server..."
curl -sSL https://get.livekit.io | bash

echo "==> Verifying LiveKit installation..."
livekit-server --version

echo "==> Opening firewall ports for LiveKit / WebRTC..."
# LiveKit RTC TCP fallback
sudo ufw allow 7881/tcp comment 'LiveKit RTC TCP'
# WebRTC media (UDP)
sudo ufw allow 50000:60000/udp comment 'LiveKit WebRTC media'
# TURN/STUN (UDP)
sudo ufw allow 3478/udp comment 'LiveKit TURN/STUN'
# TURNS TLS (TCP)
sudo ufw allow 5349/tcp comment 'LiveKit TURNS TLS'

echo "==> Reloading firewall..."
sudo ufw reload

echo "==> Configuring Redis for production..."
# Set max memory policy and limit
sudo bash -c 'cat >> /etc/redis/redis.conf << EOF

# Laradisco production tuning
maxmemory 2gb
maxmemory-policy allkeys-lru
EOF'

sudo systemctl restart redis-server

echo "==> Done! Remaining manual steps:"
echo "  1. Copy forge/livekit.yaml to /etc/livekit.yaml (fill in credentials)"
echo "  2. Create Forge daemons:"
echo "     - Horizon:        php artisan horizon"
echo "     - Reverb:          php artisan reverb:start --port=6001"
echo "     - Schedule Worker: php artisan schedule:work"
echo "     - LiveKit:         /usr/local/bin/livekit-server --config /etc/livekit.yaml"
echo "  3. Configure .env with production values"
echo "  4. Replace Nginx config with forge/nginx-site.conf"
echo "  5. Create LiveKit subdomain site with forge/nginx-livekit.conf"
echo "  6. Enable Let's Encrypt for both domains"
