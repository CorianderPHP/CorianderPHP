# Middleware Guide

Middleware handles cross-cutting request behavior such as authentication, authorization, request checks, redirects, and response headers.

Project middleware should live under `src/Middleware`. Do not put custom middleware in `CorianderCore/core/Security`; that directory is framework-owned and can be changed by framework updates.

## Directory

Recommended structure:

```txt
src/
  Middleware/
    AuthMiddleware.php
    AdminMiddleware.php
```

The framework autoloader maps `Middleware\` to `src/Middleware/`.

## Example

Create `src/Middleware/AuthMiddleware.php`:

```php
<?php
declare(strict_types=1);

namespace Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (empty($_SESSION['user_id'])) {
            return new Response(302, ['Location' => '/login']);
        }

        return $handler->handle($request);
    }
}
```

Register it in `public/routes.php` or a file from `src/Routes`:

```php
use Middleware\AuthMiddleware;

$router->group('admin', [new AuthMiddleware()], function ($router): void {
    $router->get('dashboard', fn () => 'Dashboard');
});
```

## Global Middleware

Global framework middleware is registered in `public/index.php`, for example security headers, API request limits, and CSRF protection.

Project-specific middleware should usually be registered on route groups or individual routes. This keeps small projects simple and avoids making every request pay for middleware it does not need.

## Best Practices

- Put project middleware in `src/Middleware`.
- Keep framework middleware in `CorianderCore/core`.
- Use PSR-15 `MiddlewareInterface`.
- Keep middleware focused on one concern.
- Prefer route-group middleware for feature areas like admin, account, shop, or dashboard.
- Avoid editing `CorianderCore/core/Security` for project-specific behavior.
