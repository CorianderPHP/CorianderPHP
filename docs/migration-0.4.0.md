# Upgrade to CorianderPHP 0.4.0

0.4.0 makes `src/Views` the single tree for pages and HTTP handlers. This is a breaking upgrade from 0.3.0. PHP 8.2 remains the minimum and no runtime dependency was added.

## Move application files together

Back up your application and migrate on a branch. The updater manages `CorianderCore` and `coriander`; it preserves application-owned `src`, `public`, `config`, Composer configuration and web-server files. It does not move application routes or rewrite your templates. Compare your app with the new starter and apply the changes below before deployment.

1. Move method handlers and every `_middleware.php` from `src/Routes` into the corresponding directory under `src/Views`. Resolve overlapping files before moving them; keep authentication and CSRF declarations.
2. Replace trivial GET forwarding handlers with their PHP/HTML template at the routed filename. For example, move `about.php` content to `about/index.get.php` and remove the forwarding handler for that same URL.
3. Keep redirects, JSON and non-GET handlers as request-to-response callables in the same tree.
4. Rename applicable metadata files to `_metadata.php` and return arrays. Move layout data into metadata rather than assigning it inside direct page bodies.
5. Compare the starter `src/Middleware/NotFoundMiddleware.php` and rename its template to `_notfound.php`. Copy these application-owned changes deliberately.
6. Inspect `php coriander routes:list`, verify protected pages and method responses, then remove `src/Routes` after all handlers and middleware have migrated. Check custom bootstrap directories and CLI construction too.

Keep the full PHP tree outside `public`, which contains the front controller and assets. Use the exact `src/Views` casing on case-sensitive systems. Application classes still use the `App\` namespace under `src`.

## Direct pages and method handlers

`src/Views/about/index.get.php` serves GET `/about`:

```html
<h1>About us</h1>
<p>Our website.</p>
```

Direct GET pages have no explicit return statement. They receive `$request` and the escaped metadata array `$page`. Matched parameters stay in request attributes:

```php
<h1>User <?= htmlspecialchars((string) $request->getAttribute('id'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
```

`users/[id].get.php` serves GET `/users/42`; `users/[id]/index.get.php` is an equivalent URL with a directory for local helpers. Define only one per method. Multiple parameters use directories such as `teams/[team]/users/[id].get.php`.

Any method file can return a callable receiving the request and returning a PSR response. Non-GET files must use this contract:

```php
<?php // src/Views/users/[id].post.php
use CorianderCore\Core\Http\Responses;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

return static function (ServerRequestInterface $request): ResponseInterface {
    // Validate input and perform the application's update here.
    return Responses::redirect('/users/' . rawurlencode((string) $request->getAttribute('id')));
};
```

Explicit responses bypass automatic layouts. Handlers and middleware must not print output, and a method file cannot both print HTML and return a handler. Direct GET files execute once, before header/footer execution. Variables assigned inside a page body do not become header/footer data.

An unsuffixed `index.php` is not an endpoint. Unsuffixed templates remain available through `Responses::view('template', $data)`. Explicit rendering rejects method files and reserved helper names; migrate a call such as `Responses::view('about/index.get')` to router dispatch or a separate unsuffixed template.

## Metadata and layout inheritance

```php
<?php // src/Views/_metadata.php
return ['title' => 'My website', 'description' => 'Our work'];
```

```php
<?php // src/Views/admin/_metadata.php
return ['title' => 'Administration'];
```

Metadata merges root to leaf with shallow child overrides. Whole nested arrays replace their parent value. Missing files mean an empty array; invalid returns or printed output produce an error identifying the declaration. Keep metadata free of side effects.

A header can render `<title><?= $page['title'] ?? '' ?></title>`. Metadata and explicit view-data strings are already HTML-escaped recursively. Do not escape them again. Request values remain raw and need encoding for their output context.

GET metadata loads before the selected GET file, including files returning handlers. It does not decorate their returned responses. Non-GET dispatch skips metadata unless the handler explicitly renders a view. Explicit view data can override metadata with `['page' => ['title' => 'Override']]`; `page` must be an array.

`_header.php` and `_footer.php` inherit independently from the nearest ancestor. An admin header can use the root footer; an admin footer can use the root header. A missing part is omitted. Neither part existing means only the page renders. Direct layouts receive the matched, middleware-adjusted `$request`. Explicit rendering still accepts `layout: false`.

`_middleware.php` remains a root-to-leaf array of PSR-15 middleware. Children cannot disable parent middleware. Move the declarations along with their handlers so authentication remains attached to the intended paths.

## Tooling and starter changes

### Browser assets

Move `nodejs/src/**/index.ts` into its matching GET directory under `src/Views`, or use `[id].ts` beside `[id].get.php`. Use `_script.ts` for directory-wide initialization and move reusable helpers into `src/Scripts`. Update relative imports. Shared code and types use ordinary imports; metadata does not register bundles.

Keep `moduleDetection: "force"` in the updated TypeScript configuration. Plain entries have independent local scopes; formerly implicit shared globals should become explicit exports/imports. Use `declare global` for deliberate ambient declarations.

Copy updated `nodejs/{package.json,package-lock.json,tsconfig.json,build.js,watch.all.js,tailwind.config.js}` and `public/assets/css/input.css` deliberately; remove old duplicated `build.min.js`/`build.watch.js` after updating commands. Preserve custom theme settings. Tailwind now uses explicit CSS `@source` declarations for Views and Scripts; register other app class locations as needed. Use Node 24 (CI) or supported Node 22 for tooling. The lockfile pins Tailwind 4.3.0 to keep its watcher dependency on the patched version; run a clean `npm ci` and audit when changing it.

Rebuild locally or in CI with `php coriander nodejs run build-prod` and deploy `public/assets/js/pages` and CSS. Existing apps must remove manually duplicated layout script tags after switching to automatic loading. Compiled dynamic names retain literal brackets and are reused across parameter values. Clean old `public/assets/js/home` or other unrecorded outputs manually. Preserve the build-only generated-file list between local builds; it is ignored by Git and unnecessary at runtime.

Direct GET pages automatically include existing ancestor modules and their page bundle before the footer. Returned PSR responses and fragments (`layout: false`) remain undecorated. Custom `ViewRenderer` roots may pass a matching compiled pages directory as the second constructor argument, still served at `assets/js/pages`. Missing bundles are omitted. PHP-only sites and servers deploying prebuilt assets require no Node or TypeScript source files. See [page scripts](page-scripts.md).

### PHP commands and starter

- `make:view about` creates `about/index.get.php`; `make:view index` creates the root page. Explicit GET paths are accepted. Other methods use `make:route`.
- `make:route`, `make:view` and `make:sitemap` validate definitions against current files before writing. Custom `MakeSitemap` construction takes a destination **directory**, replacing the previous filename argument.
- The starter root is `index.get.php`, with root `_metadata.php` and a private `_notfound.php`. Its redundant `/home` alias was removed. Preserve an explicit redirect if your app needs that URL.
- Custom 404 rendering preserves response headers unrelated to the replaced body and removes obsolete representation framing/validators. Existing app-owned middleware needs the starter update.
- `benchmark:router` defaults to `/`. Benchmark tools stay optional.

Route maps still rebuild automatically; no deployment build command is required. The changed default directory invalidates old-directory maps. In production, newly added or renamed routes can take up to 30 seconds to appear. Deleted cached targets fail closed. Keep OPcache timestamp validation enabled for changed PHP or restart workers during deployment; see [automatic caching](route-cache.md).

Check direct and protected pages, JSON, redirects, POST/CSRF, HEAD, 404 and 405 responses in your app. The framework's verified HTTP and paired FPM results are documented in [benchmarks](benchmarks.md). For upgrades from earlier versions, apply the [0.3.0 migration](migration-0.3.0.md) first, then this guide.
