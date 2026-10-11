# Benchmarks

## Complete production requests

On Linux with nginx, PHP-FPM, curl and Python 3 installed, run `bash CorianderCore/Tests/benchmarks/run-production.sh > benchmark-production.json`. `PHP_FPM_BINARY` defaults to `php-fpm8.3`; `BENCHMARK_REQUESTS` defaults to 80 requests per case. Composer dependencies must already be installed. These tools are optional benchmark prerequisites, not framework runtime dependencies.

The runner copies the current starter bootstrap into a disposable app, starts its own eight-worker FPM pool and nginx bound to localhost:8087, enables production caching/OPcache, and removes its processes and temporary files on exit. The fixture tests static/dynamic routes, root middleware, a three-file layout, JSON, redirects, CSRF-protected POST, 404/500 errors, and 20 ms public/session handlers under concurrency 1 and 8. POST uses a real CSRF token and session cookie established before measurement. Same-cookie public and session cases use a real session established beforehand; the session handler verifies all counter writes survive concurrency. Each case has five warmup requests and every measured response is validated, including JSON content types and redirect targets.

The 0.4.0 baseline is the production benchmark artifact from the PR that adds these cases, before changing route discovery or page execution. Keep request count, worker count, client, PHP/FPM/OPcache settings and response bodies equivalent for the final comparison. Separate hosted runners introduce timing noise; allocator peak is not process RSS.

Reports contain individual request p50/p95/p99 (nearest rank), completed throughput, PHP/FPM/OPcache metadata and PHP allocator peak bytes reported by the fixture. The memory figure excludes OS process RSS, nginx, shared OPcache and the load generator; error responses have no memory header and report null. The Python thread client and server share the machine, use new connections and closed-loop load, so these are reproducible comparisons rather than maximum server capacity or production guarantees. File-update protection is disabled for newly generated fixtures only. CI saves verified JSON artifacts without unstable absolute timing gates. Compare matching environments before changing routing or session behavior.

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

The eager-session baseline is saved in [session-eager-baseline.json](benchmarks/session-eager-baseline.json), from [production CI run 38052972449](https://github.com/CorianderPHP/CorianderPHP/actions/runs/38052972449). With eight concurrent same-cookie public requests and 20 ms of synthetic work, median latency was 164.02 ms (48.71 requests/s); the starter opened a session for every request. This shared-runner measurement is a baseline for the session change, not a general capacity claim.

The lazy-session result is saved in [session-lazy-result.json](benchmarks/session-lazy-result.json), from [production CI run 38053341764](https://github.com/CorianderPHP/CorianderPHP/actions/runs/38053341764). Both runs used PHP 8.3.35, FPM/OPcache, eight workers, 80 requests per case and the same fixture/client. They ran on separate hosted runners, so small differences are noise rather than evidence of general speed gains.

| Same-cookie case, concurrency 8 | Eager p50 | Lazy p50 | Eager requests/s | Lazy requests/s |
| --- | ---: | ---: | ---: | ---: |
| Public handler, 20 ms work | 164.02 ms | 21.12 ms | 48.71 | 365.73 |
| Session-writing handler, 20 ms holding lock | 164.15 ms | 162.09 ms | 48.64 | 49.24 |

Public handlers no longer acquire a session lock. Session-writing handlers retain the lock and all counter writes persist. Reported PHP allocator peak stayed at 2 MiB for these cases; this coarse allocator figure does not prove unchanged process RSS. See [sessions](sessions.md) for migration and explicit early lock release.
