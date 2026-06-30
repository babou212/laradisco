# Errors

> **Heads-up — two error shapes.** The API does not yet use a single error
> envelope. **Validation** errors (and errors raised through the `ApiResponse`
> trait) return a JSON:API `errors` array; **authentication, authorization, and
> not-found** errors return a simpler `{ "message": ... }` body. Handle both.
> (Normalising these onto the JSON:API envelope is a known follow-up.)

All error responses use the appropriate HTTP status code. Error handling is
defined in `bootstrap/app.php` (global handlers) and
`app/Concerns/ApiResponse.php` (controller helpers).

## JSON:API error shape (validation, 422)

Validation failures return HTTP **422** with an `errors` array. Each entry has
`status`, `title`, `detail`, and a `source.pointer` identifying the offending
field:

```json
{
  "errors": [
    {
      "status": "422",
      "title": "Validation Error",
      "detail": "The content field is required.",
      "source": { "pointer": "/data/attributes/content" }
    }
  ]
}
```

Responses raised via the `ApiResponse` trait helpers (`errorResponse`,
`forbiddenResponse`, `validationErrorResponse`, …) use this same `errors[]`
shape and set `Content-Type: application/vnd.api+json`.

## Simple message shape (auth / not-found / generic)

These handlers (in `bootstrap/app.php`) return a plain message body:

| Status | When | Body |
|---|---|---|
| `401 Unauthorized` | missing/invalid token | `{ "message": "Unauthenticated." }` |
| `403 Forbidden` | authenticated but not permitted | `{ "message": "..." }` |
| `404 Not Found` | unknown route or model | `{ "message": "Resource not found." }` |
| `406 Not Acceptable` | `Accept` header rejects JSON | (see [content negotiation](README.md#content-negotiation)) |
| `429 Too Many Requests` | rate limit exceeded | throttled; `Retry-After` header |
| 4xx/5xx (other `HttpException`) | generic | `{ "message": "..." }` |

## Client guidance

- Read the HTTP status first; branch on `errors` vs `message` by presence.
- For 422, map each `source.pointer` (`/data/attributes/<field>`) back to the
  form field to show inline validation messages.
- For 429, respect `Retry-After`.
