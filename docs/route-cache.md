# Automatic route discovery and caching

File routes are enabled by default. No deployment command is required.

In development, the router discovers filenames on each request. Set `APP_ENV=production` to persist a data-only map in `cache/routes.php`. Incoming requests refresh it after 30 seconds. New or renamed routes can take up to 30 seconds to appear; a warm request avoids a recursive directory scan. Production must have OPcache timestamp validation enabled for changed application PHP, or restart PHP workers as part of deployment.

Refreshes use a file lock and atomic replacement. Other requests can use the previous complete map during a refresh. Initial concurrent requests wait for the first build. Invalid definitions fail clearly without publishing a partial map. An unwritable cache directory falls back to discovery, keeping the application usable with reduced performance.

Maps contain relative handler paths, patterns and middleware ancestry, never closures or responses. Handlers load only after matching and middleware approval. Directory middleware is checked and loaded every request, so adding authentication takes effect immediately. Removing a handler or middleware recorded in the map fails closed until refresh; do not rely on cached routes to keep deleted files executable.

For tools and tests, `new RouteCache($routesDirectory, $cacheFile, production: true, refreshInterval: 30)` allows an explicit location and interval. `clear()` invalidates the map; normal deployment does not need it. Place the cache outside the public document root.
