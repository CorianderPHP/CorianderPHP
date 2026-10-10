# CorianderPHP 0.3.0

## File routes and simpler application structure

- Routes are discovered automatically from `src/Routes`. Use `users/[id].get.php`, `users/[id].post.php` and nested parameter directories. Each file returns a request-to-response callable.
- Directory `_middleware.php` files inherit parent middleware, so admin authentication remains active beneath child directories.
- Views live in `src/Views`. `_header.php` and `_footer.php` inherit independently: an admin header can use the shared footer, or the reverse. Missing parts are omitted, allowing header-only, footer-only and bare views. Existing complete layouts behave as before; partial layouts render instead of raising an incomplete-layout error. Fragments can skip layouts explicitly.
- `Responses::html/json/view/redirect` supply explicit PSR responses. Request parsing includes query values, form/JSON bodies, cookies and nested uploads. Malformed JSON returns 400.
- Production route maps refresh automatically on incoming requests, without a deployment command. Development discovers files every request. Cache writes are locked and atomic; missing cached application files fail closed.
- The starter and optional generators use the new structure and the `App\` namespace. Sitemaps list public URLs explicitly.
- CI covers Linux and Windows on PHP 8.2–8.5, with real HTTP checks, concurrent cache builds and verified OPcache off/on benchmark artifacts. GD cleanup no longer triggers PHP 8.5 deprecations.
- The optional Node.js asset toolchain locks `source-map-js` to patched version 1.2.2 for CVE-2026-93749. Node.js CI checks clean installs, audits and asset builds on Linux and Windows. Existing projects using this toolchain should update `nodejs/package-lock.json` and run `npm ci` from `nodejs`; PHP-only projects need no Node.js changes.

## Breaking changes and upgrade

Controllers, manual registration, automatic controller/view fallback, HTTP method attributes, controller caches and their generator/build commands are removed. `Router::handle()` replaces the old registration/dispatch workflow; `ViewRenderer::response()` replaces `render()`. Handlers must return PSR responses without printing output. Parent middleware cannot be disabled by child files.

There is no implicit `/api` CSRF exemption. Cookie-authenticated mutations need a token regardless of response format. Request limits apply to every starter route. Static paths take precedence before method selection; GET provides HEAD fallback and 405 advertises allowed methods.

PHP 8.2 remains the minimum, with no added runtime dependencies. Database, migrations, logging, sessions and asset helpers remain available. Existing apps must migrate routes, middleware, templates, namespaces and bootstrap together. The updater preserves application-owned files and does not perform this migration automatically. See [the 0.3.0 migration guide](https://github.com/CorianderPHP/CorianderPHP/blob/main/docs/migration-0.3.0.md).

## References for documentation updates

The release-published workflow dispatches `framework-released` to `CorianderPHP/Documentation`. Update the routing, HTTP, middleware, views, cache, CLI, sitemap, security and upgrade guides using:

- Framework files: `CorianderCore/core/Router/{Router,RouteMap,RouteCache,SafePath,ViewRenderer}.php`, `core/Http/{RequestFactory,Responses,BadRequestException}.php`, `core/Security/{CsrfMiddleware,ApiRequestLimitsMiddleware}.php`, `core/Console/Commands/Make/{Route,View,Sitemap}`, `core/Sitemap/SitemapHandler.php`, `CorianderCore/autoload.php`.
- App files: `public/index.php`, `src/Routes`, `src/Views`, `src/Middleware/NotFoundMiddleware.php`, `composer.json`, `.env-example`.
- Public APIs: `Router::handle`, `RouteCache::get/clear`, `ViewRenderer::response`, `Responses::html/json/redirect/view` (including `layout`), `RequestFactory::fromGlobals`, `SitemapHandler::toXml/addDynamicPage`.
- Configuration: `APP_ENV=production` enables a 30-second refresh; `PUBLIC_URL_PREFIX` defaults empty for a public document root. Existing `API_MAX_BODY_BYTES`/`API_TIMEOUT_SECONDS` remain. CSRF prefix exceptions are explicit.
- Guide sources: `docs/{routing,http,middleware,views,route-cache,cache,cli,sitemap,security,environment,modules,benchmarks,migration-0.3.0}.md` and `readme.md`.
- Validation: `.github/workflows/php.yml`, `CorianderCore/Tests/HttpIntegrationTest.php`, `RouteCacheConcurrencyTest.php` and `Tests/benchmarks/file-router.php`. Saved reports distinguish cold discovery, cached startup, repeated dispatch, layouts/middleware and actual HTTP; they do not claim production request latency.
