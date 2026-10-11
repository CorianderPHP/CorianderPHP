# File routing

Routes are discovered automatically under `src/Views`. GET files may contain PHP/HTML directly, with `$request` available for request data and parameter attributes. The framework captures the page once and wraps its HTML with independently inherited `_header.php` and `_footer.php` files. Escape raw request values for their output context.

Any method file may instead return a callable accepting `ServerRequestInterface` and returning `ResponseInterface`. Non-GET files must return a callable. Use `CorianderCore\Core\Http\Responses` for HTML, JSON, view and redirect helpers. Returned responses bypass automatic layouts. Printed output mixed with a returned callable, output from handlers/middleware, and invalid return types are errors. Direct GET templates should have no explicit return statement.

| File | Request |
| --- | --- |
| `index.get.php` | `GET /` |
| `users/[id].get.php` | `GET /users/42` |
| `users/[id].post.php` | `POST /users/42` |
| `teams/[team]/users/[id].patch.php` | `PATCH /teams/5/users/42` |
| `admin/index.get.php` | `GET /admin` |

Access parameters through `$request->getAttribute('id')`. Read query values through `getQueryParams()` and form/JSON input through `getParsedBody()`. All supported suffixes are lowercase: get, post, put, patch, delete, head and options.

Static segments take precedence before method selection. With `users/new.get.php` and `users/[id].post.php`, `POST /users/new` returns 405 rather than invoking the dynamic handler. Duplicate routes, repeated parameter names and structurally identical routes with different parameter names are definition errors. Across dynamic patterns, static segments nearer the root take precedence.

`php coriander make:route` uses these same definition rules before creating directories or writing a handler. It rejects conflicts against current files even in production, without executing handlers or middleware or updating the route cache. `users.get.php` and `users/index.post.php` can share a path with different methods; two GET definitions for that path cannot.

GET supplies a HEAD fallback. An explicit `.head.php` wins. The emitter suppresses bodies for every HEAD response, including errors and redirects. Missing paths return 404; unsupported methods return 405 with `Allow`. OPTIONS is explicit rather than automatically generated. Paths are case-sensitive; trailing slashes are accepted. URL segments decode once. Dot segments, encoded separators, backslashes, NUL and double slashes are rejected.

Dot/underscore-prefixed route files or directories are private. `_middleware.php` applies ordered PSR-15 middleware from root to leaf; see [middleware](middleware.md). Only method-suffixed files create routes. Discovery inspects filenames without executing application code. The selected method file and its rendering helpers load after middleware permits the request.

Custom bootstrap:

```php
$router = new \CorianderCore\Core\Router\Router();
$response = $router->handle($request);
\CorianderCore\Core\Http\ResponseEmitter::emit($response, $request->getMethod());
```

`Router` accepts an optional route directory and `RouteCache`. See [automatic caching](route-cache.md). Controllers, action attributes, manual registration and automatic view fallback were removed in 0.3.0.

Inspect current method files with `php coriander routes:list`. It shows parameter paths, handlers, HEAD fallbacks and inherited middleware filenames without executing application code or changing the cache.
