# Benchmarks

`php coriander benchmark:router home 1000` measures a registered GET route using seven batches of 1,000 dispatches. It checks status and body and refuses an unexpected error response. The second argument is the number of iterations per sample, replacing the former duration argument.

Reported median/p95 values are **batch means per dispatch**, not individual HTTP-request latency percentiles. Measurements include fixture verification and exclude request creation, application boot and the web server. Record PHP, OS and OPcache settings when comparing runs. CPU/memory monitoring must not dominate short operations.

The pre-change measurements are retained in docs/benchmark-baseline-0.2.json; the legacy routing script has been removed with its API. The 0.3.0 fixture harness replaces it.
