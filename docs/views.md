# Views and inherited layouts

Templates live in `src/Views`. Render them explicitly from a route:

```php
use CorianderCore\Core\Http\Responses;
return static fn($request) => Responses::view('admin/users/show', [
    'title' => 'User profile',
    'id' => $request->getAttribute('id'),
]);
```

This renders `src/Views/admin/users/show.php`. A view file never creates a route.

```text
src/Views/
  _header.php
  _footer.php
  home.php
  admin/
    _header.php
    users/show.php
```

The renderer searches from the view's directory toward `src/Views`, resolving `_header.php` and `_footer.php` independently. In this example, admin pages use the admin header and the root footer. A local footer with no local header similarly inherits the nearest parent header. Header and footer can come from different ancestor levels; each appears at most once, so layouts do not stack.

If a header or footer is absent throughout the view ancestry, that part is omitted. A header-only layout renders header then page; a footer-only layout renders page then footer. With neither file anywhere, only the page renders. Without local files, parent files still apply. `Responses::view('fragment', $data, layout: false)` explicitly skips all layout lookup. Keep inherited header and footer HTML compatible.

Header, page and footer receive the same recursively HTML-escaped string data. Use `$title`, `$id`, or other passed variables in PHP templates. Renderer internals cannot be replaced through data keys. Do not escape these variables a second time. HTML escaping does not validate URL schemes or JavaScript/CSS contexts; validate URLs and keep untrusted values out of executable contexts.

`Responses::view()` returns a PSR HTML response with a configurable status. Missing templates and unsafe paths throw clear errors rather than silently rendering another page. Every call evaluates templates again with current data. Keep reusable classes and functions in autoloaded application files.

Put assets in `public/assets`, and use `PublicUrl::versionedAsset()` or `ImageHandler::render()` for existing asset helpers. Set `PUBLIC_URL_PREFIX=/public` only when the web server serves the repository root.

The 0.3.0 starter migration removes `public/public_views`, metadata templates and automatic view fallback. Move title/description metadata into data passed by your route. Optional `make:view` creates a template directly in src/Views.
