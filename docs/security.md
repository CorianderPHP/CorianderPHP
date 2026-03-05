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

- CSRF middleware validates mutating methods: `POST`, `PUT`, `PATCH`, `DELETE`.
- Use `\CorianderCore\Core\Security\Csrf::input()` in forms.
- For JSON/API requests, validate token with the provided CSRF helpers and include token in body or expected header flow.

## SQL Safety

- Prefer map-based helpers for common conditions:
  - `findWhere`
  - `updateWhere`
  - `deleteWhere`
- Raw-string condition methods remain available for advanced SQL expressions but are not recommended for routine usage.
- For selecting all columns, prefer `findAll($table)`.

## Updater Safety

- Update archives are checked for unsafe archive paths before extraction (zip-slip defense).
- Updater source is restricted to expected GitHub release/repository patterns.
- Backup directory override (`--backup-dir`) must be a safe relative path (no traversal segments or absolute paths).

## Error Handling

- Runtime bootstrap and database initialization are wrapped to avoid leaking raw failures.
- In production, ensure `display_errors=0` and use logging/monitoring for diagnostics.

## Project Responsibilities

CorianderPHP hardens framework-level defaults, but application code remains responsible for:

- validating input data
- authorization checks
- output encoding in non-framework rendering paths
- secure secret management and environment configuration
