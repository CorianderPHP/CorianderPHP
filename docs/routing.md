# Routing Module Guide

CorianderPHP's routing system maps incoming HTTP requests to callbacks, controllers, or views. By default, controller names and the built-in router handle most paths automatically.

Custom route definitions start in `public/routes.php`. For small projects such as SPAs, marketing websites, brochure websites, and simple landing pages, keeping routes in this single file is usually the clearest option.

For larger applications, split route groups into app-owned files under `src/Routes/` and include them from `public/routes.php`.

## Small Project Routes

Custom routes are defined in `public/routes.php`. The front controller bootstraps the router and passes an instance to this file, so you can add routes directly:

```php
use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\ServerRequest;

/** @var Router $router */

$router->get('/hello/{name}', function (ServerRequest $request) {
    $name = $request->getAttribute('name');
    return new \Nyholm\Psr7\Response(200, [], "Hello {$name}");
});

$router->setNotFound(fn() => new \Nyholm\Psr7\Response(404, [], 'Not Found'));
```

The router also provides `post()`, `put()`, `patch()`, and `delete()` shortcuts.
Use `add($method, ...)` only when the method is dynamic or uncommon.

## Larger Project Route Files

Use `src/Routes/` when `public/routes.php` becomes too large or when routes naturally split by feature area, such as admin, shop, account, or API-like web endpoints.

Create a route file with:

```bash
php coriander make:route admin
```

This creates:

```txt
src/Routes/admin.php
```

The generated file returns a closure that receives the router:

```php
<?php
declare(strict_types=1);

use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

return static function (Router $router): void {
    $router->get('admin', static function (ServerRequest $request): Response {
        return new Response(200, [], 'admin route');
    });
};
```

Register it from `public/routes.php`:

```php
$adminRoutes = PROJECT_ROOT . '/src/Routes/admin.php';
if (is_file($adminRoutes)) {
    (require $adminRoutes)($router);
}
```

Nested route files are also supported:

```bash
php coriander make:route admin/users
```

This creates `src/Routes/admin/users.php`.

### Route Groups

Group routes to share a common URI prefix or middleware:

```php
use Psr\Http\Server\MiddlewareInterface;

$auth = new class implements MiddlewareInterface {
    public function process($request, $handler) {
        // authentication logic
        return $handler->handle($request);
    }
};

$router->group('/admin', [$auth], function (Router $r) {
    $r->get('/dashboard', fn (ServerRequest $req) =>
        new \Nyholm\Psr7\Response(200, [], 'Dashboard'));
});
```

Routes inside the group inherit the `/admin` prefix and the `$auth` middleware.

### Per-route Middleware

Middleware can also be attached directly when registering a route:

```php
$router->get('/profile', fn (ServerRequest $r) =>
    new \Nyholm\Psr7\Response(200, [], 'Profile'), [$auth]);
```

### Response Handling

- Route callbacks and controller actions can return a `ResponseInterface`; status code, headers, and body are preserved.
- API controller actions may return arrays, which are automatically encoded as JSON responses.
- If an API action returns text output that is not valid JSON, it is wrapped as `{"data":"..."}` and returned as JSON.

## Error Handling

- Register a `setNotFound` callback to handle unmatched routes gracefully.
- Wrap route logic in `try/catch` blocks to log and report exceptions without exposing sensitive data:

```php
$router->post('/user', function(ServerRequest $request) {
    try {
        // process request
    } catch (\Throwable $e) {
        // log and return 500 response
    }
});
```

## Best Practices

- Group related routes into separate files and include them during bootstrap to keep definitions maintainable.
- Keep `public/routes.php` for small custom route lists and for including route files from `src/Routes/`.
- Leverage PSR-15 middleware for cross-cutting concerns such as authentication or CSRF protection on mutating methods (`POST`, `PUT`, `PATCH`, `DELETE`).
- Use URL parameters instead of query strings for cleaner, cache-friendly routes.
- Avoid defining routes unless necessary; controllers are mapped automatically.

