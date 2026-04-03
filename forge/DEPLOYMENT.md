# Laradisco — Laravel Forge Deployment Guide

Deployment guide for running Laradisco on a single Hetzner server managed by Laravel Forge with FrankenPHP/Octane, Horizon, Reverb, and self-hosted LiveKit.

---

## Prerequisites

- Laravel Forge account
- Hetzner Cloud account
- Domain with DNS access (you'll need an A record for the app and a subdomain for LiveKit)
- Recommended server: **CCX33** (8 dedicated vCPUs, 32GB RAM) or minimum **CPX41** (8 shared vCPUs, 16GB RAM)

---

## Step 1: Provision the Server

1. In Forge, create a new **Hetzner** server
2. Select:
   - **PHP 8.5**
   - **PostgreSQL**
   - **Redis**
   - **Nginx**
3. Wait for provisioning to complete

---

## Step 2: Create the Application Site

1. Go to **Sites → New Site**
2. Enter your domain (e.g. `app.laradisco.com`)
3. Select **Octane** as the project type with **FrankenPHP** as the server
4. Link your Git repository
5. After creation, go to **SSL → Let's Encrypt** and enable it

---

## Step 3: Create the LiveKit Subdomain Site

1. Go to **Sites → New Site**
2. Enter the LiveKit subdomain (e.g. `livekit.laradisco.com`)
3. Select **Static HTML** as the project type (we'll replace the Nginx config)
4. After creation, go to **SSL → Let's Encrypt** and enable it

---

## Step 4: Run the Provisioning Recipe

Create a new **Recipe** in Forge (Server → Recipes) with the following script and run it on your server. This installs the LiveKit binary, opens firewall ports for WebRTC, and tunes Redis.

```bash
#!/bin/bash
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
sudo bash -c 'cat >> /etc/redis/redis.conf << EOF

# Laradisco production tuning
maxmemory 2gb
maxmemory-policy allkeys-lru
EOF'

sudo systemctl restart redis-server

echo "==> Done!"
```

---

## Step 5: Configure LiveKit

SSH into the server and create `/etc/livekit.yaml`:

```yaml
port: 7880

rtc:
  port_range_start: 50000
  port_range_end: 60000
  tcp_fallback_port: 7881
  use_external_ip: true

keys:
  YOUR_API_KEY: YOUR_API_SECRET

room:
  max_participants: 50
  empty_timeout: 300
  departure_timeout: 86400

turn:
  enabled: true
  udp_port: 3478
  tls_port: 5349
  domain: livekit.yourdomain.com
  cert_file: /etc/nginx/ssl/livekit.yourdomain.com/server.crt
  key_file: /etc/nginx/ssl/livekit.yourdomain.com/server.key

logging:
  level: info
  json: true
```

> **Important:** The `keys` values must match `LIVEKIT_API_KEY` and `LIVEKIT_API_SECRET` in your Laravel `.env`. The `domain`, `cert_file`, and `key_file` must match your actual LiveKit subdomain.

---

## Step 6: Configure Nginx

### Main Site

Go to **Sites → your-site → Nginx Configuration → Edit** and replace the config with:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name {{DOMAIN}};

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {{DOMAIN}};
    server_tokens off;
    root /home/forge/{{DOMAIN}}/public;

    # SSL managed by Forge / Let's Encrypt
    ssl_certificate /etc/nginx/ssl/{{DOMAIN}}/server.crt;
    ssl_certificate_key /etc/nginx/ssl/{{DOMAIN}}/server.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Upload limits
    client_max_body_size 120M;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header Permissions-Policy "geolocation=(), microphone=('self'), camera=()" always;
    add_header Content-Security-Policy "default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self' https: wss:; frame-ancestors 'self'; base-uri 'self'; form-action 'self';" always;

    charset utf-8;

    # Reverb WebSocket proxy
    location /app {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_pass http://127.0.0.1:6001;
    }

    # Reverb API (for broadcasting from backend)
    location /apps {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_pass http://127.0.0.1:6001;
    }

    # Internal locations for X-Accel-Redirect file serving
    location /internal-media/ {
        internal;
        alias /home/forge/{{DOMAIN}}/storage/app/private/media/;
    }

    location /internal-attachments/ {
        internal;
        alias /home/forge/{{DOMAIN}}/storage/app/private/attachments/;
    }

    # Octane proxy (main app)
    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_pass http://127.0.0.1:8000;
    }

    # Static asset caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }

    # Deny access to dotfiles
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Gzip compression
    gzip on;
    gzip_comp_level 5;
    gzip_min_length 256;
    gzip_proxied any;
    gzip_vary on;
    gzip_types
        application/javascript
        application/json
        application/xml
        application/rss+xml
        text/css
        text/javascript
        text/plain
        text/xml
        image/svg+xml;

    access_log off;
    error_log /var/log/nginx/{{DOMAIN}}-error.log error;
}
```

### LiveKit Subdomain

Go to **Sites → livekit subdomain → Nginx Configuration → Edit** and replace with:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name {{LIVEKIT_DOMAIN}};

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {{LIVEKIT_DOMAIN}};
    server_tokens off;

    ssl_certificate /etc/nginx/ssl/{{LIVEKIT_DOMAIN}}/server.crt;
    ssl_certificate_key /etc/nginx/ssl/{{LIVEKIT_DOMAIN}}/server.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";

        proxy_pass http://127.0.0.1:7880;

        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
    }

    access_log off;
    error_log /var/log/nginx/{{LIVEKIT_DOMAIN}}-error.log error;
}
```

> Replace all `{{DOMAIN}}` and `{{LIVEKIT_DOMAIN}}` placeholders with your actual domains.

---

## Step 7: Configure Daemons

In Forge, go to **Server → Daemons** and create these four daemons:

| Daemon | Command | Directory | User | Processes |
|---|---|---|---|---|
| **Horizon** | `php8.5 artisan horizon` | `/home/forge/{{DOMAIN}}` | `forge` | 1 |
| **Reverb** | `php8.5 artisan reverb:start --port=6001` | `/home/forge/{{DOMAIN}}` | `forge` | 1 |
| **Schedule Worker** | `php8.5 artisan schedule:work` | `/home/forge/{{DOMAIN}}` | `forge` | 1 |
| **LiveKit** | `/usr/local/bin/livekit-server --config /etc/livekit.yaml` | `/home/forge/{{DOMAIN}}` | `forge` | 1 |

> **Note:** Octane is managed automatically by Forge when the site type is "Octane" — no manual daemon needed. The Schedule Worker daemon is required because `presence:sweep` uses sub-minute scheduling (`everyThirtySeconds()`).

---

## Step 8: Configure Environment Variables

In Forge, go to **Sites → your-site → Environment** and set your `.env`. Key production values:

```env
APP_NAME=Laradisco
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.yourdomain.com

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laradisco
DB_USERNAME=forge
DB_PASSWORD=<generated-by-forge>

SESSION_DRIVER=redis
SESSION_CONNECTION=session
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=.yourdomain.com

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REVERB_APP_ID=laradisco
REVERB_APP_KEY=<generate-a-random-key>
REVERB_APP_SECRET=<generate-a-random-secret>
REVERB_HOST=app.yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_BROADCAST_HOST=127.0.0.1
REVERB_BROADCAST_PORT=6001
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=6001
REVERB_SCALING_ENABLED=false

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=<your-smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<your-smtp-username>
MAIL_PASSWORD=<your-smtp-password>
MAIL_FROM_ADDRESS=hello@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

LIVEKIT_URL=wss://livekit.yourdomain.com
LIVEKIT_API_KEY=<your-livekit-key>
LIVEKIT_API_SECRET=<your-livekit-secret>

HORIZON_ALLOWED_EMAILS=admin@yourdomain.com

PROMETHEUS_ALLOWED_IPS=<your-monitoring-server-ip>

CORS_ALLOWED_ORIGINS=https://app.yourdomain.com

OCTANE_HTTPS=true
```

---

## Step 9: Configure the Deployment Script

In Forge, go to **Sites → your-site → Deployments → Deploy Script** and replace with:

```bash
cd /home/forge/{{DOMAIN}}

# Pull latest code
git pull origin $FORGE_SITE_BRANCH

# Install dependencies (production-optimised)
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Run database migrations
$FORGE_PHP artisan migrate --force

# Cache configuration, routes, views, and events
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan event:cache

# Reload Octane workers gracefully (zero-downtime)
$FORGE_PHP artisan octane:reload

# Restart Horizon (daemon will auto-restart it)
$FORGE_PHP artisan horizon:terminate

# Restart Reverb WebSocket server
$FORGE_PHP artisan reverb:restart
```

---

## Step 10: Deploy & Verify

1. Click **Deploy Now** in Forge
2. Verify the deployment:

| Check | How |
|---|---|
| App is running | `curl https://app.yourdomain.com/up` → 200 |
| Horizon dashboard | Visit `/horizon` (logged in as allowed email) |
| WebSocket connection | Client connects to `wss://app.yourdomain.com/app/...` |
| LiveKit signaling | `curl https://livekit.yourdomain.com` → response |
| Voice/video calls | Join a voice channel from the client app |
| Scheduler | Check `storage/logs/laravel.log` for `presence:sweep` entries |
| File uploads | Upload a media file, verify it serves correctly |
| Prometheus metrics | `curl https://app.yourdomain.com/prometheus` from allowed IP |

---

## Architecture Overview

```
                         ┌─────────────────────────────────────────┐
                         │           Hetzner Server (Forge)        │
                         │                                         │
  Client ──── HTTPS ────▶│  Nginx (:443)                           │
                         │    ├── /        → Octane (:8000)        │
                         │    ├── /app     → Reverb (:6001) [WSS]  │
                         │    └── /apps    → Reverb (:6001)        │
                         │                                         │
  Client ── WSS ────────▶│  Nginx (:443, livekit subdomain)        │
                         │    └── /        → LiveKit (:7880)       │
                         │                                         │
  Client ── UDP ────────▶│  LiveKit RTC (:50000-60000)             │
                         │                                         │
                         │  Daemons:                               │
                         │    ├── Horizon (queue workers)          │
                         │    ├── Reverb (WebSocket server)        │
                         │    ├── Schedule Worker (sub-minute)     │
                         │    └── LiveKit Server                   │
                         │                                         │
                         │  Services:                              │
                         │    ├── PostgreSQL                       │
                         │    └── Redis (DB 0-3)                   │
                         └─────────────────────────────────────────┘
```

---

## Firewall Ports

| Port | Protocol | Purpose |
|---|---|---|
| 22 | TCP | SSH (Forge default) |
| 80 | TCP | HTTP → HTTPS redirect (Forge default) |
| 443 | TCP | HTTPS / WSS (Forge default) |
| 7881 | TCP | LiveKit RTC TCP fallback |
| 50000-60000 | UDP | WebRTC media |
| 3478 | UDP | TURN/STUN |
| 5349 | TCP | TURNS (TLS TURN) |

---

## Backups

- Enable **Forge database backups**: Server → Backups → schedule daily PostgreSQL backups
- Configure offsite storage (S3 or DigitalOcean Spaces)
- Back up `/etc/livekit.yaml` manually or via a Forge recipe

---

## Scaling Considerations

- **CPU >70% sustained during voice calls**: Move LiveKit to a separate Forge server
- **High queue load**: Increase Horizon `maxProcesses` in `config/horizon.php`
- **Database bottleneck**: Move PostgreSQL to a separate Forge database server
- **Redis memory pressure**: Increase `maxmemory` in `/etc/redis/redis.conf`
- **Multiple Reverb instances**: Re-enable `REVERB_SCALING_ENABLED=true` and run multiple Reverb daemons
