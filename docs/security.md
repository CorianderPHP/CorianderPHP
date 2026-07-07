# Security Guide

This page summarizes framework-level security behavior and recommended usage patterns.

## Routing and View Path Safety

- View rendering accepts only normalized relative paths under `public/public_views`.
- Rejected inputs include:
  - dot segments (`.` and `..`)
  - absolute paths
  - null bytes
  - Windows drive-prefixed paths
- Shared templates (`header.php`, `footer.php`) use an internally normalized view key, not raw request path input.

## CSRF Protection

- CSRF middleware validates mutating methods: `POST`, `PUT`, `PATCH`, `DELETE` (except `/api/*` by default).
- Use `\CorianderCore\Core\Security\Csrf::input()` in forms.
- For JSON/API requests, include token in JSON body as `csrf_token` when API enforcement is enabled (`CSRF_ENFORCE_API=1`).

## Proxy and TLS Detection

When CorianderPHP runs behind a reverse proxy/load balancer, HTTPS detection for secure cookies relies on `TRUSTED_PROXIES`.

- `TRUSTED_PROXIES` accepts a comma-separated list of IPs/CIDRs (default: `127.0.0.1,::1`).
- Proxy headers (`X-Forwarded-Proto`, `Forwarded`, etc.) are trusted only when `REMOTE_ADDR` matches this allowlist.
- Example: `TRUSTED_PROXIES=127.0.0.1,::1,10.0.0.0/8,192.168.0.0/16`
## Response Security Headers

`SecurityHeadersMiddleware` is enabled by default and injects a secure baseline:

- `Content-Security-Policy`
- `X-Content-Type-Options`
- `X-Frame-Options`
- `Referrer-Policy`
- `Permissions-Policy`
- `Cross-Origin-Opener-Policy`
- `Cross-Origin-Resource-Policy`
- `Strict-Transport-Security` (HTTPS requests)

Custom header policy:

- Pass custom headers to `SecurityHeadersMiddleware` where the middleware is registered.

Example:

```php
$router->addMiddleware(new SecurityHeadersMiddleware([
    'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://cdn.discordapp.com/ https://files.stripe.com/; font-src 'self'; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'",
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    'Cross-Origin-Opener-Policy' => 'same-origin',
    'Cross-Origin-Resource-Policy' => 'same-origin',
]));
```

To disable the middleware headers, pass `enabled: false` when registering it.

## API Request Limits

`ApiRequestLimitsMiddleware` is enabled by default for `/api/*` endpoints.

- Rejects payloads above `API_MAX_BODY_BYTES` (default: `1048576` bytes).
- Applies request execution/input timeout via `API_TIMEOUT_SECONDS` (default: `15`).

## SQL Safety

- Prefer map-based helpers for common conditions:
  - `findWhere`
  - `updateWhere`
  - `deleteWhere`
- Raw-string condition methods remain available for advanced SQL expressions but are not recommended for routine usage.
- For selecting all columns, prefer `findAll($table)`.

## Updater Safety

- Update archives are checked for unsafe archive paths before extraction (zip-slip defense).
- Updater source is restricted to expected GitHub download hosts.
- Updater repository is restricted by allowlist:
  - default: `CorianderPHP/CorianderPHP`
  - override with `CORIANDER_UPDATE_ALLOWED_REPOS=owner/repo,owner/repo2`
- Updater command can be hardened with:
  - `CORIANDER_UPDATER_AUTH_TOKEN` (requires `--auth-token=...`)
  - `CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR` (default: `5`)
  - `CORIANDER_UPDATER_ENABLED=0` (global disable)
  - `CORIANDER_UPDATER_ALLOW_PRODUCTION=1` (explicit production opt-in)

## Logging and Error Handling

- Runtime bootstrap and database initialization are wrapped to avoid leaking raw failures.
- Logger supports structured JSON output and file rotation for production logs.
- In production, keep `display_errors=0` and rely on logs/monitoring.

## Project Responsibilities

CorianderPHP hardens framework-level defaults, but application code remains responsible for:

- validating input data
- authorization checks
- output encoding in non-framework rendering paths
- secure secret management and environment configuration


