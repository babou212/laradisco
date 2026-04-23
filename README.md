# Laradisco

A privacy-focused, end-to-end encrypted communication platform built with Laravel.

## Features

- **End-to-End Encryption** — All messages encrypted with per-device keys using the MLS (Messaging Layer Security) protocol
- **Channels & Categories** — Organized text and voice channels with topics and granular permissions
- **Direct Messages** — Private 1-on-1 and group conversations, fully encrypted
- **Threads** — Threaded replies within channels and DMs
- **Voice & Video** — Real-time voice/video channels powered by LiveKit
- **Real-Time** — WebSocket-driven presence, typing indicators, and instant message delivery via Laravel Reverb
- **RBAC** — 30+ permission flags with per-channel overrides (admin, moderator, member, etc.)
- **Device Management** — Register and revoke devices, identity key rotation, encrypted key backup with lockout protection
- **Audit Logging** — Immutable, SHA-256 hash-chained E2EE audit trail
- **Encrypted Attachments** — Files encrypted end-to-end, stored on S3
- **Reactions, Pins, Mentions** — Emoji reactions, pinned messages, and @mention notifications
- **Invite Links** — Token-based controlled server access

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.5 |
| Framework | Laravel 13 |
| Auth | Fortify (login/register/2FA) + Sanctum (API tokens) |
| Database | PostgreSQL 18 |
| Real-Time | Laravel Reverb (WebSocket) with Redis scaling |
| Voice/Video | LiveKit |
| HTTP Server | FrankenPHP + Laravel Octane |
| Queue | Redis + Laravel Horizon |
| Storage | Spatie MediaLibrary |
| Encryption | E2EE + MLS protocol |
| Monitoring | Laravel Nightwatch |

## Requirements

- PHP 8.5+
- Composer
- Redis
- Docker & Docker Compose (local development)

## Getting Started

### With Docker (recommended)

```bash
cp .env.example .env
docker compose up -d
docker compose exec laravel.test composer setup
```

This spins up all services: the app (FrankenPHP/Octane), Reverb (WebSocket), Horizon (queues), PostgreSQL, Redis, LiveKit, and Mailpit.

## Services (Docker Compose)

| Service | Port | Description |
|---|---|---|
| `laravel.test` | 80 | FrankenPHP + Octane app server |
| `reverb` | 8080 | WebSocket server |
| `redis` | 6379 | Cache, sessions, queues |
| `pgsql` | 5432 | PostgreSQL database |
| `livekit` | 7880 | Voice/Video media server |
| `mailpit` | 1025 / 8025 | SMTP catcher + web UI |

## API Overview

All API routes are prefixed with `/v1` and require `auth:sanctum` unless noted otherwise.

**Auth** — `POST /v1/auth/login`, `/register`, `/two-factor-challenge`, `GET /v1/auth/me`

**Channels** — CRUD channels, list messages (`GET /v1/channels/{channel}/messages`), send messages, edit, delete

**Direct Messages** — List DM groups, find/create 1-on-1, send messages

**Threads** — View and reply to threads within channel messages

**Reactions & Pins** — Toggle emoji reactions, pin/unpin messages

**Voice** — Join/leave voice channels with LiveKit token generation

**E2EE** — Register identity keys and devices, upload/fetch MLS key packages, manage MLS groups, key backup/restore

**Presence & Notifications** — Online status, typing indicators, mention alerts

## Testing

```bash
sail composer test
```

Runs Pint lint check followed by the PHPUnit test suite.

```bash
sail composer lint          # Auto-fix code style
sail composer test:lint     # Check code style without fixing
```

## Static Analysis

```bash
./vendor/bin/phpstan analyse
```

Configured via `phpstan.neon` with Larastan.

## CI

A single GitHub Actions workflow (`.github/workflows/ci.yml`) runs on every push and PR to `develop` and `main`:

- **Lint** — `vendor/bin/pint --test`
- **Test** — PHPUnit with Postgres + Redis service containers
- **Security** — Snyk dependency vulnerability scan

## Deployment

Production is deployed via [Laravel Forge](https://forge.laravel.com) with auto-deploy on push to `main`.

Forge runs `forge/deploy.sh` which handles:

- `composer install` (production-optimised)
- Database migrations
- Config, route, view, and event caching
- Octane reload (zero-downtime)
- Horizon and Reverb restart

See `forge/DEPLOYMENT.md` for the full provisioning and setup guide.

## License

AGPL-3.0-or-later
