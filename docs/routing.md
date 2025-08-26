# Routing Module Guide

CorianderPHP's routing system maps incoming HTTP requests to callbacks, controllers, or views. By default, controller names and the built-in router handle most paths automatically. Custom route definitions are only needed for atypical URLs and live in `public/routes.php`.

## Configuration

Custom routes are defined in `public/routes.php`. The front controller bootstraps the router and passes an instance to this file, so you can add routes directly:

```php
use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\ServerRequest;

/** @var Router $router */

$router->add('GET', '/hello/{name}', function (ServerRequest $request) {
    $name = $request->getAttribute('name');
    return new \Nyholm\Psr7\Response(200, [], "Hello {$name}");
});

$router->setNotFound(fn() => new \Nyholm\Psr7\Response(404, [], 'Not Found'));
```

## Error Handling

- Register a `setNotFound` callback to handle unmatched routes gracefully.
- Wrap route logic in `try/catch` blocks to log and report exceptions without exposing sensitive data:

```php
$router->add('POST', '/user', function(ServerRequest $request) {
    try {
        // process request
    } catch (\Throwable $e) {
        // log and return 500 response
    }
});
```

## Best Practices

- Group related routes into separate files and include them during bootstrap to keep definitions maintainable.
- Leverage PSR-15 middleware for cross-cutting concerns such as authentication or CSRF protection.
- Use URL parameters instead of query strings for cleaner, cache-friendly routes.
- Avoid defining routes unless necessary; controllers are mapped automatically.

