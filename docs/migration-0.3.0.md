# Upgrade to CorianderPHP 0.3.0

0.3.0 changes the routing and rendering APIs. PHP 8.2 remains the minimum; no runtime package has been added. Upgrade framework and application code together on a branch, verify your protected routes, then deploy.

## 1. Preserve application code and update the framework

Back up your application and review `php coriander update --dry-run`. The updater manages `CorianderCore` and `coriander`; it preserves `src`, `public`, `config`, Composer configuration, `.env` and web-server files. It copies current framework files but does not prune every obsolete file absent from a release. A clean checkout of 0.3.0 is the simplest starting point: bring your application files across and compare them with the new starter. Do not rely on the updater alone to complete this breaking migration.

If updating in place, remove unused legacy framework routing files after verifying your app no longer references them: `Router/RouteDispatcher.php`, `RouteRegistry.php`, `HttpMethods.php`, `NameFormatter.php`, `Router/Handlers/*`, `Router/Services/ControllerCacheService.php` and `Console/Commands/Make/Controller/*`, all under `CorianderCore/core`. Remove the old view metadata generator template as well. Keep any custom code for migration before deleting it.

## 2. Replace registrations and controllers with method files

A previous registration such as `$router->get('users/{id}', $callback)` becomes `src/Routes/users/[id].get.php`:

```php
<?php
declare(strict_types=1);
use CorianderCore\Core\Http\Responses;
use Psr\Http\Message\ServerRequestInterface;

return static function (ServerRequestInterface $request) {
    $id = $request->getAttribute('id');
    // Validate the identifier, authorize access and call an application service.
    return Responses::json(['id' => $id]);
};
```

Move each controller action to its method file. Extract shared logic into ordinary `App\` services rather than duplicating it across handlers. Return a PSR response explicitly; arrays, strings, echoed output and global `header()` calls are no longer the handler contract. Use `Responses::json/html/view/redirect`, or return a Nyholm response. Replace `{id}` with `[id]` filenames. Custom regex and automatic action/model binding are not supported; validate parameter formats in middleware or the handler. Catch-all/optional segments are deferred.

`index.get.php` maps to a directory root. Multiple parameters are separate directories, for example `teams/[team]/users/[id].post.php`. All supported suffixes are listed in the [routing guide](routing.md). Static paths take precedence before selecting a method, so a method mismatch never becomes a dynamic route. GET supplies HEAD fallback; explicit HEAD wins. OPTIONS requires its own file. 404 and 405 are distinct.

Remove `public/routes.php`, controller namespaces, HTTP method attributes, `automaticRouting` flags and registration calls once their behavior is migrated. Views no longer supply fallback routes. For a homepage alias, create `home.get.php` returning the handler from `index.get.php` explicitly.

## 3. Move middleware to route directories

A previous `/admin` route group becomes `src/Routes/admin/_middleware.php`:

```php
<?php
return [new \App\Middleware\AuthMiddleware()];
```

Keep classes in `src/Middleware` with namespace `App\Middleware`. Root middleware applies first, then each directory's ordered array. Parent authentication cannot be removed by a child returning `[]`. Parameters are available as request attributes; middleware can deny access before the handler file loads.

Compare your root `_middleware.php` with the starter: security headers, configured request limits, CSRF and an app-owned HTML 404 middleware. The request-limit class keeps its `ApiRequestLimitsMiddleware` name and existing configuration keys; `apiPrefixes: []` applies it to every route.

There is no implicit `/api` CSRF exemption. Cookie-authenticated POST/PUT/PATCH/DELETE requests need a token even when returning JSON. Use `Csrf::input()` for forms or send `csrf_token` in parsed JSON. Only explicitly stateless authentication should use a configured prefix exemption. The starter starts sessions for all paths; custom bootstraps may explicitly pass stateless prefixes to `SessionBootstrap::startForRequest()`.

## 4. Move templates and layouts

Move `public/public_views/home/index.php` to `src/Views/home.php`. Move the shared header/footer to `_header.php`/`_footer.php` under `src/Views`. Each file under `src/Views/admin` overrides its corresponding parent file independently: an admin header can use the shared footer, and an admin footer can use the shared header. Layouts do not stack. Files absent throughout the view ancestry are omitted, allowing header-only, footer-only and bare views. Existing complete layouts behave as before; partial layouts now render instead of raising an incomplete-layout error.

Replace metadata includes with explicit view data:

```php
return Responses::view('admin/dashboard', [
    'title' => 'Administration',
    'description' => 'Manage your application',
]);
```

Header, page and footer share recursively HTML-escaped string data. Avoid double escaping and keep untrusted values out of executable JavaScript/CSS contexts. Pass `layout: false` for fragments. Direct renderer users should use `ViewRenderer::response()`; the former `render()` method was removed. Missing views throw rather than silently falling back.

Sitemaps now list public URLs explicitly through `SitemapHandler::addDynamicPage()` and `toXml()`. Replace `fetchStaticPages()`/metadata scanning. Keep protected routes out of the list. `make:sitemap` generates `src/Routes/sitemap.xml.get.php`.

## 5. Update bootstrap, autoload and server configuration

Compare your `public/index.php` with the starter. Include configuration using an absolute `__DIR__` path, create the request using `RequestFactory`, call `Router::handle()`, and emit using the original method so HEAD bodies are suppressed. Keep error responses generic and log failures. Middleware replaces `setNotFound` for a custom 404 page.

Replace application namespace mappings in Composer with:

```json
"App\\": "src/"
```

Rename application class namespaces accordingly, including middleware/modules. Keep the CorianderCore mappings. Run `composer dump-autoload` after namespace changes. Generators remain optional: `make:route "users/[id].post"` creates a discovered handler; `make:view admin/profile` creates a private template. `make:controller`, cache `controllers`/`all` and the old benchmark duration argument were removed.

Prefer `public` as the web-server document root, with missing paths routed to `index.php` and direct PHP execution restricted to the front controller. Keep `src`, configuration, framework code, caches and secrets outside it. Apache repository-root deployments must compare the supplied root `.htaccess`; nginx needs equivalent configuration. Delete old publicly served templates/route-registration scripts after migration. `PUBLIC_URL_PREFIX` is empty for a public document root and `/public` for repository-root deployments. Configure `PROJECT_URL` for absolute sitemap URLs.

## 6. Deploy and verify

Set `APP_ENV=production`. Incoming requests create and refresh the data-only route map automatically after 30 seconds; no deployment build command is needed. New/renamed routes may take that interval to appear. Middleware candidates are checked on every request, independently of map freshness. Missing cached handlers/middleware fail closed. An unwritable cache falls back to discovery, with a performance cost. Apply your host's normal OPcache timestamp validation or worker restart policy when replacing application PHP.

Run the framework suite and application-specific checks:

```bash
vendor/bin/phpunit --testdox
php coriander benchmark:router home 1000
```

Verify authenticated and unauthenticated admin requests, form/JSON mutations, redirects, HEAD, 404/405, layouts and uploads. Use the [benchmark guide](benchmarks.md) for repeatable measurements. Database, migration, session, logger and asset APIs are otherwise retained.
