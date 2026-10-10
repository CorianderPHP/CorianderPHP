# Cache

Route maps refresh automatically on incoming requests when `APP_ENV=production`. Development discovers files every request. See [route caching](route-cache.md) for locking, freshness and deployment behavior.

`php coriander cache clear` is an optional diagnostic command. The next request rebuilds automatically. Controller caches and the `controllers`/`all` build commands were removed in 0.3.0.
