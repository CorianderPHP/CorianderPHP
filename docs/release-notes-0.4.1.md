# CorianderPHP 0.4.1

Framework metadata now uses `$metadata`, leaving `$page` available for application page or document data.

## Changes

- Direct GET pages, headers and footers receive inherited `_metadata.php` values through `$metadata`.
- `Responses::view()` accepts explicit metadata overrides through `['metadata' => [...]]`. This key must be an array and replaces inherited keys shallowly before one HTML-escaping pass.
- The `page` data key accepts ordinary application values, including objects, arrays, scalars and null. It does not override metadata. Objects retain their identity and properties; encode their values when displaying them.
- The starter header and 404 middleware use the new contract. Current view and security guides include the updated names and escaping rules.
- Request URIs now include scheme, host and port while preserving encoded paths and queries. Host headers take precedence over server fallback values; HTTPS uses the existing trusted-proxy policy. Forwarded hosts and proxy listener ports are ignored, malformed hosts produce 400, and hostless injected requests retain relative URIs.

Metadata inheritance, optional files, layouts and scripts keep their existing behavior. PHP 8.2 remains the minimum; runtime dependencies are unchanged. The renderer uses the same loading and escaping path with renamed variables and keys.

## Upgrade from 0.4.0

**This patch includes a breaking metadata naming change.** Update application-owned templates and handlers alongside the framework:

1. Rename metadata reads such as `$page['title']` to `$metadata['title']` in GET files, headers, footers and explicit templates. Keep `$page` wherever it represents application data.
2. Change metadata overrides from `['page' => ['title' => 'Override']]` to `['metadata' => ['title' => 'Override']]`. Review custom 404 middleware and other `Responses::view()` callers.
3. Keep `_metadata.php` filenames and returned arrays as they are. The updater preserves `src`, so apply these application changes yourself before deployment.

For example, a handler can pass a document and metadata separately:

```php
return static fn($request) => Responses::view('documentation', [
    'page' => $document,
    'metadata' => ['title' => 'Documentation'],
]);
```

The header uses the already escaped metadata:

```php
<title><?= $metadata['title'] ?? '' ?></title>
```

The template can use `$page` as its application document. Strings inside view-data arrays are escaped recursively; object properties and request values remain raw and require output-context encoding. There is no automatic `$page` metadata alias.

## Documentation references

- Public contracts: `Responses::view()` and `ViewRenderer::response()` use the `metadata` data key; `ViewRenderer::dispatch()` exposes `$metadata` to direct GET files and layouts.
- Framework and starter files: `CorianderCore/core/Router/ViewRenderer.php`, `CorianderCore/core/Http/Responses.php`, `src/Views/_header.php`, `src/Middleware/NotFoundMiddleware.php`.
- Updated guides: `docs/views.md`, `docs/security.md` and `readme.md`. Historical 0.4.0 guides link to this migration.
- HTTP: `RequestFactory::fromGlobals()` in `CorianderCore/core/Http/RequestFactory.php` now populates the URI authority and scheme. See `docs/http.md`; applications that reconstruct absolute URLs can use `getUri()` directly.
