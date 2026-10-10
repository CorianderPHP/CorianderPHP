# Environment Variables

CorianderPHP loads simple runtime variables from `.env`.

When `.env` is missing and `.env-example` exists, the framework copies `.env-example` to `.env` before loading variables. The generated `.env` file is ignored by Git.

New applications default to `APP_ENV=production` and `APP_DEBUG=0`. Missing files or missing keys use the same defaults. For local development, set `APP_ENV=local` and `APP_DEBUG=1` in `.env` to see exception details and discover route changes on every request. Existing `.env` files and explicit server settings are preserved.

## Supported Syntax

```env
APP_ENV=production
APP_DEBUG=0
APP_TIMEZONE=Europe/Paris
PROJECT_URL=http://localhost
PUBLIC_URL_PREFIX=
```

Supported:

- empty lines
- comments starting with `#`
- `KEY=value`
- `export KEY=value`
- single-quoted values
- double-quoted values with `\n`, `\r`, `\t`, `\"`, and `\\`
- inline comments after unquoted values, such as `APP_ENV=local # comment`

The loader does not parse complex shell syntax, arrays, multiline values, or variable expansion.

## Override Behavior

Existing server or shell environment variables are not overwritten by `.env`.

This keeps production deployment variables in control while preserving a simple local developer workflow.

## Automatic route caching in 0.3.0

`APP_ENV=production` enables automatic persistence and a 30-second refresh on incoming requests. Other environments discover route filenames on each request. A deployment route-build command is not required; `cache clear` is optional. See [route caching](route-cache.md).

The starter applies `API_MAX_BODY_BYTES` and `API_TIMEOUT_SECONDS` to every route through root middleware. Set `PUBLIC_URL_PREFIX=` when `public` is the document root. Repository-root deployments use `/public` and equivalent server access restrictions. The example environment now defaults to a public document root. Existing `.env` files are preserved; update these values deliberately during migration.

## Debug errors

`APP_DEBUG=1` enables the built-in exception page only when `APP_ENV` is `local` or `development`. The page escapes exception details and omits trace argument values. Production, staging and unknown environments always return generic errors, even with debug enabled. `ErrorResponse::fromException()` handles 400/413/500 for custom bootstraps.
