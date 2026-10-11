# CorianderPHP 0.4.0 development

A lightweight PHP framework focused on simple developer UX, performance and few dependencies. Routes are PHP files, middleware follows directories, and views inherit headers and footers independently. Application code lives in `src`; framework code lives in `CorianderCore`.

The 0.4.0 rework uses one `src/Views` tree for templates and method handlers. Existing 0.3.0 applications must move handlers and `_middleware.php` declarations from `src/Routes` into this tree, resolving filename conflicts before removing the old directory. See the [0.4.0 execution contract and roadmap](docs/0.4.0-design.md) for the remaining implementation steps.

## Start

PHP 8.2 or newer is required.

```bash
composer install
php -S localhost:8000 -t public public/index.php
```

Open `/`. New applications default to production with debugging disabled and automatic route caching. For local development, set `APP_ENV=local` and `APP_DEBUG=1` in the automatically created `.env`. No route build command is required. Node.js is optional for rebuilding the bundled assets; see [Node.js](docs/nodejs.md).

## Create a page

Create `src/Views/about/index.get.php`:

```html
<h1>About us</h1>
<p>Visit /about. No forwarding handler is needed.</p>
```

GET pages render directly with inherited layouts. `$request` exposes request data and matched parameters; escape raw values when displaying them in HTML. Files without a method suffix are not endpoints.

## Handle a request

`src/Views/users/[id].get.php`:

```php
<?php
use CorianderCore\Core\Http\Responses;
use Psr\Http\Message\ServerRequestInterface;
return static fn(ServerRequestInterface $request) => Responses::json([
    'id' => $request->getAttribute('id'),
]);
```

Use `.post.php`, `.put.php`, `.patch.php`, `.delete.php`, `.head.php` or `.options.php` for other methods. `index.get.php` handles its directory root. Query values, parsed bodies, cookies and uploads use PSR request methods. Handlers return PSR responses; use `Responses::html`, `json`, `view` or `redirect`.

`src/Views/admin/_middleware.php` returns an array of PSR-15 middleware. Parents always apply, so child directories inherit authentication. Application classes use `App\` under `src`.

Middleware protects direct pages before loading them. Their headers and footers also receive the matched `$request`, including attributes added by middleware.

Optional `_metadata.php` files return arrays: parent defaults inherit, child keys override, and GET pages/layouts read the escaped data through `$page`. Missing metadata means an empty array. See [views](docs/views.md) for escaping and explicit overrides.

`Responses::view('admin/profile', $data)` renders `src/Views/admin/profile.php`, using the nearest `_header.php` and nearest `_footer.php` independently. A local header can use the root footer, and a local footer can use the root header. Either part is omitted if absent throughout the view ancestry; when neither exists, only the view renders. Pass `layout: false` for fragments.

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

`make:route` checks the same definitions as discovery and rejects repeated parameters and conflicting routes before writing files.

Generated views contain minimal content. Add forms and `Csrf::input()` when needed; ordinary views do not open a session. Construct application services directly and pass shared dependencies through their constructors.

## Validation

Local development uses `APP_ENV=local` and `APP_DEBUG=1` for useful exception pages. Production always uses generic errors; see [environment configuration](docs/environment.md).

Request bodies are limited by `API_MAX_BODY_BYTES` before application parsing; oversized requests return 413. See the [completed stabilization roadmap](docs/0.3.0-stabilization.md) for the fixes, individual issues and PRs. The [benchmark guide](docs/benchmarks.md) records the complete FPM request comparison for lazy sessions.

```bash
vendor/bin/phpunit --testdox
```

Runtime dependencies remain Nyholm PSR-7 and PSR HTTP/logging interfaces. PHPUnit is a development dependency. Database, session, migration, logging and asset helpers remain available; keep business logic in ordinary application services.

For framework development, follow [the contributor guide](docs/contributing.md): native types first, useful PHPDoc contracts and comments explaining constraints.
