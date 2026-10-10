# Benchmarks

`php coriander benchmark:router home 1000` measures a registered GET route using seven batches of 1,000 dispatches. It checks status and body and refuses an unexpected error response. The second argument is the number of iterations per sample, replacing the former duration argument.

Reported median/p95 values are **batch means per dispatch**, not individual HTTP-request latency percentiles. Measurements include fixture verification and exclude request creation, application boot and the web server. Record PHP, OS and OPcache settings when comparing runs. CPU/memory monitoring must not dominate short operations.

`php CorianderCore/Tests/benchmarks/router-baseline.php` exercises deterministic 10/100/1,000-route fixtures before the 0.3.0 router replacement. Use separate measurements for first discovery, new-request cache loading, warm dispatch, middleware/layout rendering and HTTP traffic. Use repeated runs on the same environment; shared CI runner timing is informational, not a fixed-speed correctness gate.
