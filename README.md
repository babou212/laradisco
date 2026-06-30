# Laradisco

A privacy-focused communication platform built with Laravel.

## Features

- **Channels & Categories** — Organized text and voice channels with topics and granular permissions
- **Direct Messages** — Private 1-on-1 and group conversations
- **Threads** — Threaded replies within channels and DMs
- **Voice & Video** — Real-time voice/video channels powered by LiveKit
- **Real-Time** — WebSocket-driven presence, typing indicators, and instant message delivery via Laravel Reverb
- **RBAC** — 30+ permission flags with per-channel overrides (admin, moderator, member, etc.)
- **Attachments** — Stored on S3
- **Reactions, Pins, Mentions** — Emoji reactions, pinned messages, and @mention notifications
- **Invite Links** — Token-based controlled server access

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.5 |
| Framework | Laravel 13 |
| Auth | Fortify (login/register/2FA) + Sanctum (API tokens) |
| Database | PostgreSQL 18 |
| Real-Time | Laravel Reverb (WebSocket) |
| Voice/Video | LiveKit |
| HTTP Server | FrankenPHP + Laravel Octane |
| Queue | Redis + Laravel Horizon |
| Storage | Spatie MediaLibrary |
| Monitoring | Laravel Nightwatch |

## Requirements

Local development runs entirely in Docker via **Laravel Sail** — you only need:

- Docker & Docker Compose
- (Optional) PHP 8.5 + Composer on the host, only if you prefer to bootstrap without the helper below

## Local Development (Sail)

### First-time setup

```bash
cp .env.example .env
docker compose up -d                              # build & start all services
docker compose exec laravel.test composer setup   # install deps, key:generate, migrate, build assets
```

`composer setup` installs PHP and JS dependencies, generates the app key, runs migrations, and builds the frontend.

### The `sail` alias

The repo ships the Sail wrapper at `vendor/bin/sail`. Add an alias so day-to-day commands are short:

```bash
alias sail='vendor/bin/sail'
```

After that you can use `sail up -d`, `sail down`, `sail artisan …`, `sail composer …`, and `sail npm …` instead of the longer `docker compose …` forms.

### Day-to-day

```bash
sail up -d                  # start the stack
sail artisan migrate        # run migrations
sail artisan tinker         # REPL
sail composer test          # lint + test suite
sail down                   # stop the stack
```

Once running, the app is at **http://localhost**, with hot-reloaded assets when `sail npm run dev` is active.

## Services (Docker Compose)

| Service | Port(s) | Description |
|---|---|---|
| `laravel.test` | 80 | FrankenPHP + Octane app server |
| `reverb` | 8080 | WebSocket server |
| `queue` | — | Horizon queue worker |
| `redis` | 6379 | Cache, sessions, queues |
| `pgsql` | 5432 | PostgreSQL database |
| `meilisearch` | 7700 | Full-text search (Scout) |
| `livekit` | 7880–7882 | Voice/Video media server |
| `garage` | 3900 / 3903 | S3-compatible object storage (attachments) |
| `mailpit` | 1025 / 8025 | SMTP catcher + web UI |

Web UIs: app → http://localhost · Mailpit → http://localhost:8025 · API docs → http://127.0.0.1:8088/docs/index.html

## API Documentation

The API follows the [JSON:API](https://jsonapi.org) standard and is documented with [Scribe](https://scribe.knuckles.wtf/laravel).

**View the reference** (interactive, with a "Try It Out" console):

- Browse: **http://localhost/docs**
- OpenAPI spec: http://localhost/docs.openapi
- Postman collection: http://localhost/docs.postman

Access is internal-only (`App\Http\Middleware\DocsAccess`): always available in local/dev, and in production only when `DOCS_ENABLED=true`.

**Conventions guide** (the source of truth for cross-cutting rules — auth, the response envelope, query params, pagination, idempotency, errors) lives in [`docs/api/`](docs/api/README.md). Read it before the endpoint reference.

**Regenerate** after changing any route, form request, or resource:

```bash
sail artisan scribe:generate
```

The per-endpoint reference is produced from controller docblocks (`@group`, `@queryParam`, `@response`, …); generation is database-free, so it runs anywhere including CI.

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


## License

AGPL-3.0-or-later
