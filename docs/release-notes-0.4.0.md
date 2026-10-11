# CorianderPHP 0.4.0

CorianderPHP now uses one `src/Views` tree for pages and HTTP handlers. Simple websites can place PHP/HTML directly in `about/index.get.php` without a forwarding route file.

## Breaking changes

- The default route directory moves from `src/Routes` to `src/Views`. Move handlers and `_middleware.php` declarations together; the updater preserves application files and does not perform this migration.
- Only method-suffixed files are endpoints. `about/index.get.php`, `users/[id].get.php` and `users/[id].post.php` follow the same discovery rules. Unsuffixed templates remain explicitly renderable.
- Direct GET pages render once with inherited layouts. Other methods return request-to-response callables. GET callables remain suitable for JSON and redirects; returned responses bypass automatic layouts.
- `Responses::view()` rejects method files and reserved helpers. The `page` data key must be an array.
- `make:view` now generates direct GET pages, normally `name/index.get.php`. Custom `MakeSitemap` construction accepts a directory rather than a filename. The starter removes the redundant `/home` alias and renames its 404 template to `_notfound.php`.
- Browser entries move from `nodejs/src` beside GET pages in `src/Views`; generated modules mirror that tree under `public/assets/js/pages`. Update imports, asset paths and manually duplicated layout tags. The old separate build/minify/watch implementations are consolidated in `nodejs/build.js`.

## Developer experience

- Optional `_metadata.php` arrays inherit root to leaf with shallow child overrides. Pages and layouts read recursively HTML-escaped metadata through `$page`.
- `_header.php` and `_footer.php` inherit independently. Missing parts are omitted, and direct layouts receive the matched, middleware-adjusted `$request`.
- Directory middleware runs before pages; children retain parent authentication. Raw request parameters need output encoding; escaped metadata/view data should not be escaped again.
- Generators share discovery validation, preserve existing files and reject unsafe destinations, including dangling symlinks.
- Custom 404 rendering preserves unrelated response headers while replacing body-specific headers and validators.
- Optional `index.ts`/`[id].ts` entries pair with GET pages. Directory `_script.ts` modules accumulate root to leaf, followed by the page module. Missing bundles are omitted; modules use encoded, versioned asset URLs and work without Node or source files on the server.
- Reuse helpers such as `src/Scripts/menu.ts` and shared types through ordinary imports. No metadata registration or separate helper bundles are required.
- Plain TypeScript entries have independent module scopes, so local variable names can be reused across pages without boilerplate exports. Former implicit shared globals need explicit imports or intentional ambient declarations.
- Watch handles new, renamed and removed entries, shared edits and type-error recovery. Type checks emit no JavaScript; failed checks preserve last good output. Cleanup removes only previously recorded generated bundles, preserving manual JS.
- The PHP `nodejs run watch-all` wrapper keeps watcher input open, preventing immediate successful exit before watching begins.
- Tailwind explicitly scans Views and Scripts. Asset CI uses Node 24 and current Node 24 action runtimes; Node 22 remains supported locally. The audited lockfile updates TypeScript/esbuild and retains a safe Tailwind/watcher combination.

GET metadata is evaluated before the selected file, including callable GET handlers. Keep declarations free of side effects. Explicit responses are not decorated; non-GET dispatch skips metadata unless its handler explicitly renders a view.

## Performance and compatibility

Automatic discovery and production cache refresh continue without a deployment build command. Discovery does not execute application files. The rework reuses the route map, renderer, output capture and PSR response APIs; no runtime dependency was added. PHP 8.2 remains the minimum.

Real HTTP tests cover nested parameters, metadata escaping, redirects, HEAD, private helper isolation and invalid output. Cache tests cover automatic added/removed-page discovery and stale missing targets. Production CI compares equivalent baseline and current requests on the same nginx/FPM runner with OPcache and eight workers; response validation is required. Timing is informational, and reported PHP allocator peaks exclude process RSS and shared OPcache. See [benchmark results](https://github.com/CorianderPHP/CorianderPHP/blob/main/docs/benchmarks.md).

Upgrade application code with the framework. See the [0.4.0 migration guide](https://github.com/CorianderPHP/CorianderPHP/blob/main/docs/migration-0.4.0.md).

## Documentation references

- Runtime: `CorianderCore/core/Router/{Router,RouteCache,ViewRenderer,SafePath}.php`; new `ViewRenderer::dispatch(name, request)`, default `src/Views`, explicit view restrictions and inherited metadata.
- Script loading: optional `ViewRenderer` constructor `scriptDirectory`, inherited compiled modules, encoded/versioned URLs, fragment/explicit-response behavior. Build tooling remains in `nodejs`; page entries move to Views and ordinary imports to Scripts. See `docs/{page-scripts,nodejs}.md`, `nodejs/{build.js,watch.all.js,tsconfig.json}`, and `public/assets/css/input.css`.
- CLI: `CorianderCore/core/Console/Commands/Make/{Route/MakeRoute,View/MakeView,Sitemap/MakeSitemap}.php`, Routes and BenchmarkRouter commands. Update generator paths, GET examples and the custom sitemap constructor.
- Starter: `src/Views/{index.get,_metadata,_header,_footer,_middleware,_notfound,sitemap.xml.get}.php` and `src/Middleware/NotFoundMiddleware.php`. Existing application-owned files require manual migration.
- Guides: `docs/{routing,views,middleware,cli,route-cache,security,sitemap,benchmarks,migration-0.4.0,0.4.0-design}.md` and `readme.md`.
- Verification: `CorianderCore/Tests/{DirectPagesTest,PageMetadataTest,HttpIntegrationTest,RouteCacheTest,ViewLayoutsTest,GeneratorPathTest}.php` and `.github/workflows/production-benchmark.yml`.
