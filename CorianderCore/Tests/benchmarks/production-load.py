"""Verified closed-loop HTTP load using only Python's standard library."""
import concurrent.futures
import http.client
import json
import math
import os
import platform
import sys
import time
import urllib.parse


def request(port, path, cookie="", method="GET", body=None):
    started = time.perf_counter()
    connection = http.client.HTTPConnection("127.0.0.1", port, timeout=10)
    try:
        headers = {"Cookie": cookie} if cookie else {}
        if body is not None:
            headers["Content-Type"] = "application/x-www-form-urlencoded"
        connection.request(method, path, body=body, headers=headers)
        response = connection.getresponse()
        body = response.read().decode("utf-8")
        return response.status, body, {name.lower(): value for name, value in response.getheaders()}, (time.perf_counter() - started) * 1000
    finally:
        connection.close()


def run(port, count):
    if count < 1:
        raise ValueError("Request count must be positive")
    _, body, metadata, _ = request(port, "/")
    if body != "static" or metadata.get("x-php-sapi") != "fpm-fcgi" or metadata.get("x-opcache") != "1":
        raise RuntimeError("Benchmark requires verified PHP-FPM with OPcache")
    report = {"benchmark": "nginx-fpm", "source_commit": os.environ.get("BENCHMARK_COMMIT"), "php": metadata["x-php-version"], "sapi": metadata["x-php-sapi"],
              "opcache": True, "os": platform.platform(), "client_python": platform.python_version(),
              "workers": 8, "request_count_per_case": count, "cases": []}
    cases = [("static", "/", 200, "static"), ("dynamic", "/users/42", 200, "user:42"),
             ("layout", "/layout", 200, "<header>header</header><main>body</main><footer>footer</footer>"),
             ("json", "/json", 200, '{"name":"fixture"}'), ("redirect", "/redirect", 303, ""),
             ("post", "/form", 200, '{"name":"fixture"}'),
             ("404", "/missing", 404, "404 Not Found"), ("500", "/failure", 500, "Internal Server Error"),
             ("public_same_cookie_delay", "/public-delay", 200, "public"),
             ("session_same_cookie_hold", "/session/hold", 200, None)]
    for concurrency in [1, 8]:
        for name, path, status, expected in cases:
            _, _, headers, _ = request(port, "/session/start")
            cookie = headers["set-cookie"].split(";", 1)[0] if "same_cookie" in name else ""
            method, body = "GET", None
            if name == "post":
                token_status, token, token_headers, _ = request(port, "/form")
                if token_status != 200:
                    raise RuntimeError("CSRF token setup failed")
                cookie = token_headers["set-cookie"].split(";", 1)[0]
                method = "POST"
                body = urllib.parse.urlencode({"csrf_token": token, "name": "fixture"})
            for _ in range(5):
                warm = request(port, path, cookie, method, body)
                if warm[0] != status or (expected is not None and warm[1] != expected):
                    raise RuntimeError(f"Warmup failed: {name}")
            started = time.perf_counter()
            with concurrent.futures.ThreadPoolExecutor(max_workers=concurrency) as clients:
                results = list(clients.map(lambda _: request(port, path, cookie, method, body), range(count)))
            elapsed = time.perf_counter() - started
            values = []
            for response_status, response_body, response_headers, latency in results:
                if response_status != status or (expected is not None and response_body != expected):
                    raise RuntimeError(f"Unexpected response: {name} {response_status} {response_body!r}")
                if name != "500":
                    active = "1" if name in ("session_same_cookie_hold", "post") else "0"
                    if response_headers.get("x-session-active") != active:
                        raise RuntimeError(f"Unexpected session startup: {name}")
                    if active == "0" and "set-cookie" in response_headers:
                        raise RuntimeError(f"Public request created a cookie: {name}")
                if name in ("json", "post") and not response_headers.get("content-type", "").startswith("application/json"):
                    raise RuntimeError(f"Unexpected content type: {name}")
                if name == "redirect" and response_headers.get("location") != "/":
                    raise RuntimeError("Unexpected redirect target")
                values.append(latency)
            if expected is None and sorted(json.loads(result[1])["count"] for result in results) != list(range(6, count + 6)):
                raise RuntimeError("Concurrent session writes lost updates")
            values.sort()
            peaks = [int(result[2]["x-php-peak-bytes"]) for result in results if "x-php-peak-bytes" in result[2]]
            report["cases"].append({"name": name, "method": method, "path": path, "concurrency": concurrency, "requests_per_second": count / elapsed,
                "p50_ms": values[math.ceil(count * .50) - 1], "p95_ms": values[math.ceil(count * .95) - 1],
                "p99_ms": values[math.ceil(count * .99) - 1], "max_php_allocator_peak_bytes": max(peaks) if peaks else None,
                "session_active": results[0][2].get("x-session-active")})
    return report


if __name__ == "__main__":
    report = run(int(sys.argv[1]), int(sys.argv[2]) if len(sys.argv) > 2 else 80)
    print(json.dumps(report, indent=2))
