# Benchmarks

`php coriander benchmark:router home 1000` measures a discovered GET handler using seven batches of 1,000 dispatches. It checks status and body and refuses unexpected errors. The second argument is iterations per sample.

Run the fixture suite with:

```bash
php CorianderCore/Tests/benchmarks/file-router.php benchmark-results.json
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 CorianderCore/Tests/benchmarks/file-router.php benchmark-results-opcache.json
```

It verifies 10/100/1000 total routes (half static, half dynamic), discovery, a new router loading a persisted map, repeated static/dynamic/404 dispatch and middleware with a three-file layout. It also sends actual HTTP requests through PHP's development server. Every measured route checks status and body; middleware cases verify response headers.

Median and p95 are **batch means per operation**, not individual HTTP latency percentiles. Cached request startup includes reading the map and dispatch, but excludes creating the PSR request and process/application boot. Repeated dispatch reuses one router. HTTP includes socket/server/bootstrap costs, with development discovery enabled in the fixture. Discovery uses single-operation batches. Memory is the process-wide peak, not allocation per request. The OPcache run disables file-update protection for its freshly generated disposable fixtures. Reports include PHP, OS and OPcache settings; compare matching environments.

## Initial Windows results

The saved [PHP 8.2 Windows report](benchmark-results-0.3-windows-php82.json) used OPcache disabled. Median batch means, rounded to microseconds:

| Routes | Discovery | Cached startup + dispatch | Repeated static | Repeated dynamic | Middleware + layout |
| --- | ---: | ---: | ---: | ---: | ---: |
| 10 | 1,671 | 193 | 90 | 108 | 429 |
| 100 | 14,840 | 492 | 94 | 128 | 458 |
| 1000 | 145,420 | 3,365 | 88 | 184 | 437 |

This demonstrates why production avoids recursive discovery. It also shows that loading a large PHP map without OPcache has a cost. These are fixture timings from one Windows machine; they do not establish production page latency or a speed advantage over other frameworks. The HTTP fixture averaged roughly 21 ms in this environment, including development-server and socket overhead.

The [0.2 baseline](benchmark-baseline-0.2.json) used in-memory manually registered closures. It excludes the new handler-file and cache loading, so it is historical context rather than a like-for-like performance claim. Its old registration script was removed with the API.

CI records reports with OPcache CLI off/on on Linux and Windows across PHP 8.2–8.5 and uploads them as artifacts. Response verification is required; no unstable absolute timing threshold blocks CI.

## Linux CI example

The [PHP 8.5 Linux report with OPcache](benchmark-results-0.3-linux-php85-opcache.json) and [matching report without it](benchmark-results-0.3-linux-php85.json) came from the same verified CI run. For 1,000 routes, cached startup plus dispatch measured about 9.3 microseconds with warmed OPcache versus 2,175 microseconds without it. Repeated static dispatch measured about 7.0 versus 13.7 microseconds. These CLI fixtures exclude process/application startup. Fresh-fixture file-update protection is disabled only in the OPcache benchmark command; production deployments use their normal PHP lifecycle policy.
