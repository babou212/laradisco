# Authentication

The API authenticates with **Laravel Sanctum personal access tokens** (bearer
tokens). There are no session cookies for API consumers. The relevant routes are
in `routes/api/auth.php` and the logic in
`app/Http/Controllers/Api/AuthController.php`.

## Obtaining a token

`POST /api/v1/auth/register` or `POST /api/v1/auth/login` returns a plain-text
token. Send it on every authenticated request:

```
Authorization: Bearer <token>
```

```bash
curl -X POST https://<host>/api/v1/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"you@example.com","password":"secret","device_name":"web"}'
```

The token is minted by `AuthController::issueToken()`
(`$user->createToken($deviceName)->plainTextToken`). Treat it as a secret; it
does not expire automatically.

## Public (unauthenticated) endpoints

These do not require a token:

- `GET  /api/v1/ping` — service status + Reverb connection config
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/two-factor-challenge`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `GET  /api/v1/auth/invite/{token}` — validate an invite link

All auth endpoints are rate-limited (`throttle:5,1`).

## Two-factor authentication

If a user has 2FA enabled, `login` returns a challenge response instead of a
token. Complete it with `POST /api/v1/auth/two-factor-challenge` (a `code` or
`recovery_code`) to receive the token. 2FA enrolment/management lives under
`/api/v1/settings/two-factor/*` (`Settings/TwoFactorController`).

## Authenticated session helpers

- `GET  /api/v1/auth/me` — the current user
- `POST /api/v1/auth/logout` — revoke the current token

## Authorization

Beyond authentication, most actions are gated by role/permission checks
(`spatie/laravel-permission`, `PermissionService`, policies). A permitted token
acting outside its permissions receives **`403 Forbidden`** (see
[Errors](errors.md)). Banned/jailed users are enforced in real time.
