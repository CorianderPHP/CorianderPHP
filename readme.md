# CorianderPHP 0.3.0

A lightweight PHP framework focused on simple developer UX, performance and few dependencies. Routes are PHP files, middleware follows directories, and views inherit headers and footers independently. Application code lives in `src`; framework code lives in `CorianderCore`.

## Start

PHP 8.2 or newer is required.

```bash
composer install
php -S localhost:8000 -t public public/index.php
```

Open `/`. Node.js is optional for rebuilding the bundled assets; see [Node.js](docs/nodejs.md). Set `APP_ENV=production` for automatic route caching. No route build command is required.

## Create a route

`src/Routes/users/[id].get.php`:

```php
<?php
use CorianderCore\Core\Http\Responses;
use Psr\Http\Message\ServerRequestInterface;
return static fn(ServerRequestInterface $request) => Responses::json([
    'id' => $request->getAttribute('id'),
]);
```

Use `.post.php`, `.put.php`, `.patch.php`, `.delete.php`, `.head.php` or `.options.php` for other methods. `index.get.php` handles its directory root. Query values, parsed bodies, cookies and uploads use PSR request methods. Handlers return PSR responses; use `Responses::html`, `json`, `view` or `redirect`.

`src/Routes/admin/_middleware.php` returns an array of PSR-15 middleware. Parents always apply, so child directories inherit authentication. Application classes use `App\` under `src`.

`Responses::view('admin/profile', $data)` renders `src/Views/admin/profile.php`, using the nearest `_header.php` and nearest `_footer.php` independently. A local header can use the root footer, and a local footer can use the root header. Either part is omitted if absent throughout the view ancestry; without both, only the view renders. Pass `layout: false` for fragments.

## Guides

- [Upgrade to 0.3.0](docs/migration-0.3.0.md), [release notes](docs/release-notes-0.3.0.md)
- [Routing](docs/routing.md), [HTTP requests and responses](docs/http.md)
- [Middleware](docs/middleware.md), [views](docs/views.md), [security](docs/security.md)
- [Automatic caching](docs/route-cache.md), [benchmarks](docs/benchmarks.md)
- [CLI](docs/cli.md), [database and migrations](docs/database.md)
- [Application services/modules](docs/modules.md), [sitemap](docs/sitemap.md)
- [Environment](docs/environment.md), [Node.js](docs/nodejs.md)
- [Sessions](docs/sessions.md): public requests stay session-free; CSRF and application code start sessions when needed.

## Optional tooling

```bash
php coriander make:route "users/[id].post"
php coriander make:view admin/profile
php coriander make:migration CreateUsersTable
php coriander migrate
php coriander cache clear
php coriander routes:list
php coriander benchmark:router home 1000
php coriander help
```

Creating route and view files manually works the same way. `php coriander update` updates framework-managed files while preserving application code. Breaking upgrades require application changes; review the migration guide before updating an existing project.

## Validation

Local development uses `APP_ENV=local` and `APP_DEBUG=1` for useful exception pages. Production always uses generic errors; see [environment configuration](docs/environment.md).

Request bodies are limited by `API_MAX_BODY_BYTES` before parsing; oversized requests return 413. See the [stabilization roadmap](docs/0.3.0-stabilization.md) for the remaining 0.3.0 work.

```bash
vendor/bin/phpunit --testdox
```

Runtime dependencies remain Nyholm PSR-7 and PSR HTTP/logging interfaces. PHPUnit is a development dependency. Database, session, migration, logging and asset helpers remain available; keep business logic in ordinary application services.
