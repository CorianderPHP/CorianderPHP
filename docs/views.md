# Views and inherited layouts

Pages and handlers live in `src/Views`. A simple GET page needs only its PHP/HTML method file:

```html
<!-- src/Views/about/index.get.php -->
<h1>About</h1>
<p>Our website.</p>
```

It serves `/about` with inherited layouts. Dynamic pages use `$request->getAttribute('id')` for `[id]` parameters. Values read from the request are raw; use `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` for ordinary HTML text/quoted attributes. URL and JavaScript contexts require their own encoding. The page executes once, before header/footer execution, so explicit handler responses can bypass layouts; provide header data through `_metadata.php` or explicit view data rather than variables assigned inside the page.

Automatic direct-page headers and footers also receive `$request`, including matched parameters and attributes added by middleware. The framework chooses each layout part from its closest ancestor independently. A dynamic file such as `users/[id].get.php` uses the helpers in `users`; `users/[id]/index.get.php` can also use helpers inside `[id]`.

Unsuffixed templates can still be rendered explicitly from a handler:

```php
use CorianderCore\Core\Http\Responses;
return static fn($request) => Responses::view('admin/users/show', [
    'title' => 'User profile',
    'id' => $request->getAttribute('id'),
]);
```

This renders `src/Views/admin/users/show.php`. Unsuffixed templates are not endpoints; method files in this same tree are discovered as routes.

`Responses::view()` rejects method files such as `index.get.php`; dispatch those through the router. Returned callable handlers must not print output. Non-GET files cannot render HTML directly.

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

## Optional page metadata

`_metadata.php` returns an array. Ancestor files load root to leaf; child keys replace parent keys shallowly, including whole nested arrays. Missing files mean an empty array. Declarations must not print output; invalid files report their relative path. Keep declarations free of side effects.

```php
<?php // src/Views/_metadata.php
return ['title' => 'My website', 'description' => 'Our work'];
```

```php
<?php // src/Views/about/_metadata.php
return ['title' => 'About us'];
```

Direct GET pages and their layouts receive this data as `$page`. For example, the header can use `<title><?= $page['title'] ?? '' ?></title>`. Metadata strings are already HTML-escaped recursively; do not escape them again. Request attributes remain raw. Metadata keys stay inside `$page` and cannot overwrite `$request` or internal file variables.

Metadata is evaluated before the GET file, including GET files returning a callable. Returned PSR responses are not automatically decorated. Non-GET callable dispatch skips metadata; explicitly calling `Responses::view()` loads it for that view. Explicit view data can override metadata through `['page' => ['title' => 'Override']]`; `page` must be an array and is merged before one escaping pass. This also applies to explicit rendering with `layout: false`.

New, edited or removed metadata files are reflected on the next request even with a cached route map. Legacy `metadata.php` is not automatically loaded; rename it to `_metadata.php` and migrate its contents to the array contract.

The 0.4.0 tree stays outside the public document root. Move old handlers and templates into `src/Views`, resolving filename conflicts. Existing explicit view data remains supported alongside inherited metadata.
