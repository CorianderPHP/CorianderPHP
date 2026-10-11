# Benchmarks

## Page scripts: optional asset and renderer measurements

Run `node nodejs/benchmarks/build.js` after installing optional npm dependencies. It verifies one directory entry plus 1/20/100 page entries importing a shared helper, warms each fixture once, and records three minified rebuild samples including discovery/publishing. Type checking, npm/process startup, Tailwind and browser execution are excluded. Reported gzip sizes compress each bundle separately. CI uploads reports on Node 24 for Linux and Windows; no timing threshold gates merges.

The [local Windows build report](benchmarks/page-scripts-build-windows.json) used Node 22.18.0 and esbuild 0.28.2 against runtime commit `c2a5779c8f306b76f4664133928210870dc181ec`. Median rebuilds were 23.9/39.2/104.6 ms for 1/20/100 pages. These deliberately tiny fixtures generated 87/1,142/5,622 total JS bytes; a single page selected 87 bytes across its page and root modules. Tiny gzip output is larger because of compression headers. This verifies selected bundles remain independent of total page count, not a production bundle-size promise.

Run `php CorianderCore/Tests/benchmarks/page-scripts.php` for verified renderer batch means. CI enables CLI OPcache; the [local Windows report](benchmarks/page-scripts-renderer-windows.json) used PHP 8.2.10 with OPcache unavailable. Seven samples of 200 renders include constructor, metadata/layout checks, response creation and validation. They exclude application/process startup, HTTP, JS transfer and browser execution.

| Local renderer case | Median batch mean µs |
| --- | ---: |
| Missing asset directory | 222.7 |
| Existing directory, missing bundles | 319.4 |
| Three selected bundles | 375.5 |
| Same three bundles with 500 unrelated assets | 381.1 |

The latter pair renders identical bodies and checks only current ancestors. The small difference is not evidence of a filesystem-wide scan or a general performance bound. Asset-free versus scripted cases intentionally produce different bodies, so they are feature-cost scenarios rather than an equivalent-response before/after comparison. Each case reported a 4 MiB process allocator peak; this coarse figure includes the fixture and excludes process RSS and shared OPcache. The paired FPM workflow measures equivalent PHP-only responses separately, including actual individual HTTP latency percentiles.



### Paired PHP-only FPM result

The [pre-script baseline](benchmarks/page-scripts-paired-baseline.json) and [current result](benchmarks/page-scripts-paired-result.json) come from [CI run 38158814490](https://github.com/CorianderPHP/CorianderPHP/actions/runs/38158814490), testing PR #241's merge checkout. Both used PHP 8.3.35/FPM/OPcache, eight workers and 80 requests per case on the same runner, with identical verified PHP-only response bodies and fresh server processes.

| Case | Concurrency | Baseline p50 ms | Script rework p50 ms | Baseline p95 ms | Script rework p95 ms |
| --- | ---: | ---: | ---: | ---: | ---: |
| static | 1 | 0.886 | 0.783 | 1.020 | 1.023 |
| dynamic | 1 | 0.795 | 0.795 | 0.890 | 0.817 |
| layout | 1 | 0.802 | 0.773 | 0.844 | 0.833 |
| static | 8 | 3.528 | 3.414 | 6.369 | 5.633 |
| dynamic | 8 | 3.504 | 3.638 | 6.025 | 6.148 |
| layout | 8 | 3.667 | 3.318 | 6.291 | 5.845 |

The single-client medians for these cases differ by -0.103 to effectively zero milliseconds; concurrency-eight medians differ by -0.349 to +0.134 ms. These small fixtures and shared-runner load cannot establish a universal speed gain or regression bound. Successful responses report the same 2 MiB PHP allocator peak; this excludes process RSS/shared OPcache, and error cases report null. Scripted renderer costs and JS bytes are measured separately above.

## Complete production requests

On Linux with nginx, PHP-FPM, curl and Python 3 installed, run `bash CorianderCore/Tests/benchmarks/run-production.sh > benchmark-production.json`. `PHP_FPM_BINARY` defaults to `php-fpm8.3`; `BENCHMARK_REQUESTS` defaults to 80 requests per case. Composer dependencies must already be installed. These tools are optional benchmark prerequisites, not framework runtime dependencies.

The runner copies the current starter bootstrap into a disposable app, starts its own eight-worker FPM pool and nginx bound to localhost:8087, enables production caching/OPcache, and removes its processes and temporary files on exit. The fixture tests static/dynamic routes, root middleware, a three-file layout, JSON, redirects, CSRF-protected POST, 404/500 errors, and 20 ms public/session handlers under concurrency 1 and 8. POST uses a real CSRF token and session cookie established before measurement. Same-cookie public and session cases use a real session established beforehand; the session handler verifies all counter writes survive concurrency. Each case has five warmup requests and every measured response is validated, including JSON content types and redirect targets.

The [0.4.0 baseline](benchmarks/unified-pages-baseline.json) comes from [production CI run 38147258670](https://github.com/CorianderPHP/CorianderPHP/actions/runs/38147258670), before changing route discovery or page execution. Keep request count, worker count, client, PHP/FPM/OPcache settings and response bodies equivalent for the final comparison. Separate hosted runners introduce timing noise; allocator peak is not process RSS. Final static/dynamic/layout cases use direct GET pages with equivalent responses; JSON/redirect/POST cases retain callable handlers. The layout's header/footer move into its directory so unrelated pages keep their original bare responses.

The historical unified-pages comparison uses fixed baseline `72fff84956f0a22eb65eb9eb81aa1f59c746a40a`. Current production CI checks out pre-script commit `d2432347cbb6b4030c8cfde68200111b755791eb` and runs it before current code on the same runner, with independent Composer installations and fresh nginx/FPM processes. Its PHP-only fixtures have no compiled assets and verify identical response bodies. Both reports are uploaded together. This reduces machine differences, but sequential execution and shared host load still introduce noise; it is not an absolute performance gate.

Reports contain individual request p50/p95/p99 (nearest rank), completed throughput, PHP/FPM/OPcache metadata and PHP allocator peak bytes reported by the fixture. The memory figure excludes OS process RSS, nginx, shared OPcache and the load generator; error responses have no memory header and report null. The Python thread client and server share the machine, use new connections and closed-loop load, so these are reproducible comparisons rather than maximum server capacity or production guarantees. File-update protection is disabled for newly generated fixtures only. CI saves verified JSON artifacts without unstable absolute timing gates. Compare matching environments before changing routing or session behavior.

`php coriander benchmark:router / 1000` measures a discovered GET handler using seven batches of 1,000 dispatches. It checks status and body and refuses unexpected errors. The second argument is iterations per sample.

Run the fixture suite with:

```bash
php CorianderCore/Tests/benchmarks/file-router.php benchmark-results.json
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 CorianderCore/Tests/benchmarks/file-router.php benchmark-results-opcache.json
```

It verifies 10/100/1000 total routes (half static, half dynamic), discovery, a new router loading a persisted map, repeated static/dynamic/404 dispatch and middleware with a three-file layout. It also sends actual HTTP requests through PHP's development server. Every measured route checks status and body; middleware cases verify response headers.

Median and p95 are **batch means per operation**, not individual HTTP latency percentiles. Cached request startup includes reading the map and dispatch, but excludes creating the PSR request and process/application boot. Repeated dispatch reuses one router. HTTP includes socket/server/bootstrap costs, with development discovery enabled in the fixture. Discovery uses single-operation batches. Memory is the process-wide peak, not allocation per request. The OPcache run disables file-update protection for its freshly generated disposable fixtures. Reports include PHP, OS and OPcache settings; compare matching environments.

## Unified pages: paired 0.4.0 results

The historical reports in this section retain their original pre-routing baseline; the page-script comparison uses the newer pre-script baseline described above.

The [paired baseline](benchmarks/unified-pages-paired-baseline.json) and [unified-pages result](benchmarks/unified-pages-result.json) come from [CI run 38150348575](https://github.com/CorianderPHP/CorianderPHP/actions/runs/38150348575). Both used PHP 8.3.35/FPM/OPcache, eight workers, Python 3.12.3 and 80 requests per case on the same Linux runner, with fresh server processes. Request paths, methods, bodies, session behavior and load were equivalent and verified. The result commit is GitHub's tested PR merge checkout.

| Case | Concurrency | Baseline p50 ms | 0.4.0 p50 ms | Baseline p95 ms | 0.4.0 p95 ms |
| --- | ---: | ---: | ---: | ---: | ---: |
| static | 1 | 0.662 | 0.709 | 0.742 | 0.828 |
| dynamic | 1 | 0.664 | 0.719 | 0.775 | 0.777 |
| layout | 1 | 0.686 | 0.720 | 0.839 | 0.767 |
| json | 1 | 0.668 | 0.704 | 0.792 | 0.762 |
| redirect | 1 | 0.618 | 0.685 | 0.720 | 0.752 |
| post | 1 | 0.732 | 0.791 | 0.808 | 0.945 |
| static | 8 | 2.790 | 2.355 | 4.714 | 4.251 |
| dynamic | 8 | 2.635 | 2.617 | 4.528 | 4.644 |
| layout | 8 | 2.634 | 2.426 | 4.846 | 4.081 |
| json | 8 | 2.933 | 2.725 | 4.804 | 4.095 |
| redirect | 8 | 2.622 | 2.591 | 4.567 | 4.550 |
| post | 8 | 3.178 | 3.358 | 5.130 | 4.921 |

Single-client median latency increased by about 0.03–0.07 ms across these tiny-response cases; concurrency-eight results vary in both directions. These observations include the renderer changes and shared-host/client noise and cannot isolate their individual costs. They do not establish a universal speed gain or regression bound. With eight concurrent requests, the 20 ms public/session workloads remain around 22/163 ms respectively, retaining lazy-session behavior and all session counter writes.

Successful cases report a 2 MiB PHP allocator peak in both reports. This coarse figure excludes process RSS and shared OPcache; error cases without the measurement header report null. Repeat equivalent runs on deployment hardware before setting a latency or capacity budget.

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
