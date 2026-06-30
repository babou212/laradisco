# Introduction

Internal reference for the Laradisco HTTP API. The API follows the JSON:API standard (https://jsonapi.org) and is built on Laravel's native JSON:API resources.

<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>

Every endpoint lives under the `/api/v1` prefix and speaks [JSON:API](https://jsonapi.org).
Before using the reference below, read these cross-cutting conventions — the full guide is in the repo at `docs/api/`.

**Authentication.** The API uses Laravel Sanctum bearer tokens. Obtain one from `POST /api/v1/auth/login`
(or `/auth/register`) and send it as `Authorization: Bearer <token>` on every authenticated request.

**Content negotiation.** You must send `Accept: application/json` (or `application/vnd.api+json`).
Requests that accept neither receive `406 Not Acceptable`.

**Response envelope.** Single resources are returned as `{ "data": { "type", "id", "attributes", "relationships" } }`
and collections as `{ "data": [ ... ], "meta": { ... } }`. Related resources appear in a top-level `included`
array only when requested via `?include=` (comma-separated, e.g. `?include=user,reactions`).

**Query features** (powered by Spatie Query Builder): `include`, `filter[field]`, and `sort` (comma-separated,
prefix `-` for descending). Each list endpoint documents its own allowed includes / filters / sorts.

**Pagination.** Most list endpoints use page-based pagination (`?page=N`) with `meta`/`links`. Message and
direct-message timelines instead use a custom anchored window (`?before=`, `?after=`, `?around=` message id,
plus `?limit=`) and return `meta.has_more_before`, `meta.has_more_after`, `meta.oldest_id`, `meta.newest_id`.

**Idempotency.** For write endpoints that create resources (sending messages, creating DMs/threads, invite
links), send a unique `Idempotency-Key` header to make retries safe.

**Rate limiting.** Authenticated traffic is limited to 100 requests/minute per user; auth and other sensitive
actions are throttled more tightly. Exceeding a limit returns `429 Too Many Requests`.

**Errors.** Validation failures (`422`) return a JSON:API `errors` array, each with `status`, `title`,
`detail`, and a `source.pointer` (e.g. `/data/attributes/content`). Authentication (`401`), authorization
(`403`), and not-found (`404`) responses currently return a simpler `{ "message": "..." }` body.

