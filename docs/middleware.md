# Middleware in 0.3.0

Place application middleware classes in `src/Middleware`. Each `src/Routes/**/_middleware.php` returns an ordered array of PSR-15 `MiddlewareInterface` instances:

```php
<?php
use App\Middleware\AuthMiddleware;
return [new AuthMiddleware()];
```

A request for `src/Routes/admin/users/[id].get.php` runs middleware from the routes root, `admin`, then `admin/users`, in that order. Middleware sees the matched parameters through `$request->getAttribute('id')`. It may return a response immediately to deny access or redirect, or call `$handler->handle($request)` and modify its response.

Parent middleware always applies. A child file returning `[]` cannot disable its parent's authentication. Response middleware unwinds in reverse order. Root middleware also wraps 404 and invalid-path responses; directory middleware wraps a matched path's 405 response.

Discovery reads filenames without executing handlers or middleware. Middleware files load on each request, including newly added files when a production route map is cached. Missing middleware recorded in a cached map fails closed. Return middleware objects; printed output and malformed declarations are errors.

The starter puts security headers, request limits and CSRF protection in the root `_middleware.php`. CSRF protection has no implicit `/api` exemption. JSON output does not make a cookie-authenticated endpoint stateless. Explicit prefix exceptions are available for applications that use separate stateless authentication.

These conventions replace manual route-group registration in 0.3.0. Application middleware uses the App namespace under src.

Session authentication middleware must call `SessionBootstrap::start()` before accessing `$_SESSION`. The starter configures cookies without eager startup; CSRF helpers open sessions automatically. See [sessions](sessions.md).
