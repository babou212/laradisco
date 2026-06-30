# Laradisco API — Developer Guide

This directory is the **source of truth** for the cross-cutting conventions of the
Laradisco HTTP API. The browsable per-endpoint reference is generated from the
codebase by [Scribe](https://scribe.knuckles.wtf/laravel) and served at **`/docs`**
(internal-only — see [Accessing the docs](#accessing-the-generated-reference)).

Read this guide first; it explains the rules that the per-endpoint reference
assumes you already know.

- [Authentication](authentication.md)
- [Query features (include / filter / sort)](query-features.md)
- [Pagination](pagination.md)
- [Errors](errors.md)

## At a glance

| | |
|---|---|
| **Base URL** | `https://<host>/api/v1` |
| **Standard** | [JSON:API](https://jsonapi.org), via Laravel's native `JsonApiResource` |
| **Auth** | Sanctum personal access tokens (`Authorization: Bearer <token>`) |
| **Required header** | `Accept: application/json` (or `application/vnd.api+json`) |
| **Versioning** | Single version, `v1`, in the URL path |
| **Rate limit** | 100 req/min per authenticated user (tighter on auth/sensitive routes) |

## Base URL & versioning

Every endpoint lives under the `/api/v1` prefix (`routes/api.php`,
`routes/api/*`). There is currently a single API version; when a
breaking change is needed, a new `/api/v2` prefix will be introduced rather than
mutating `v1` in place.

A public, unauthenticated `GET /api/v1/ping` returns service status and the
Reverb (WebSocket) connection parameters.

## Content negotiation

Requests **must** declare they accept JSON. The `EnsureJsonAccept` middleware
(`app/Http/Middleware/EnsureJsonAccept.php`) returns **`406 Not Acceptable`**
unless the `Accept` header allows `application/json` or
`application/vnd.api+json`. Always send:

```
Accept: application/json
Content-Type: application/json
```

## Response envelope

Responses follow JSON:API. A single resource:

```json
{
  "data": {
    "type": "messages",
    "id": "123",
    "attributes": { "content": "hello", "created_at": "2026-06-30T12:00:00Z" },
    "relationships": {
      "user": { "data": { "type": "users", "id": "7" } }
    }
  }
}
```

A collection returns `data` as an array, usually alongside a `meta` object (see
[Pagination](pagination.md)). Resource shapes are defined by the classes in
`app/Http/Resources/` (e.g. `MessageResource`, `UserSummaryResource`) — each
declares its `type`, `attributes`, and `relationships`.

Related resources are returned in a top-level **`included`** array **only when you
ask for them** with `?include=` (see [Query features](query-features.md)).
Without `include`, relationships are present as resource-identifier objects
(`{ "type", "id" }`) but their full attributes are not embedded.

## Idempotency

Write endpoints that create a resource — sending a message, creating a DM or
thread reply, creating an invite link — accept an **`Idempotency-Key`** request
header (`app/Http/Middleware/IdempotencyKey.php`). Send a unique key (e.g. a
UUID) per logical action; a retry with the same key returns the original
response instead of creating a duplicate. Message creation additionally supports
a `client_temp_id` body field for client-side dedupe.

## Rate limiting

Limits are defined in `app/Providers/RateLimitServiceProvider.php`:

| Bucket | Limit | Applies to |
|---|---|---|
| `api` / `api-messages` / `api-search` | 100/min per user (or IP) | general traffic, messaging, search |
| `api-auth` | 100/min per IP | auth endpoints |
| route throttles (`throttle:5,1`, `6,1`, `10,1`) | 5–10/min | login, register, password, role changes, etc. |

Exceeding a limit returns **`429 Too Many Requests`** with the standard
`Retry-After` / `X-RateLimit-*` headers.

## Realtime (WebSockets)

Live updates (new messages, presence, typing, bans) are delivered over
**Laravel Reverb** WebSockets, not by polling the HTTP API. Connection
parameters come from `GET /api/v1/ping`; clients authenticate private/presence
channels through `POST /api/v1/broadcasting/auth`. Channel definitions live in
`routes/channels.php`. (These WS concerns are out of scope for the HTTP
reference and are intentionally excluded from the generated docs.)

## Frontend note (Wayfinder)

The SPA generates typed route helpers with **Wayfinder** (`@/actions`,
`@/routes`). Those cover *call sites* — URL + method for a route. This API
documentation covers the *contract* — request parameters, headers, and response
shapes. Use both together.

## Accessing the generated reference

The reference is generated with `php artisan scribe:generate` and served at
`/docs` (with `/docs.openapi` and `/docs.postman` for the machine-readable
artifacts). Access is gated by `app/Http/Middleware/DocsAccess.php`: always
available outside production, and in production only when `DOCS_ENABLED=true`.

**Regenerate the docs whenever you add or change a route, request, or resource:**

```bash
php artisan scribe:generate
```

The generated output is driven by docblock annotations in the controllers
(`@group`, `@authenticated`/`@unauthenticated`, `@queryParam`, `@apiResource`).
When adding an endpoint, annotate its controller method the same way as its
siblings so it appears correctly in the reference.
