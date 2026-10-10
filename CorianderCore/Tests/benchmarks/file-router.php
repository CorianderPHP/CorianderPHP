<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once PROJECT_ROOT . '/CorianderCore/autoload.php';

use CorianderCore\Core\Benchmark\BenchmarkHandler;
use CorianderCore\Core\Router\{RouteCache, RouteMap, Router};
use CorianderCore\Tests\Support\HttpServer;
use Nyholm\Psr7\ServerRequest;

$root = PROJECT_ROOT . '/CorianderCore/Tests/_tmp_benchmark_' . bin2hex(random_bytes(5));
mkdir($root, 0775, true);
$benchmark = new BenchmarkHandler();
$results = ['description' => 'Verified batch means in microseconds; not individual-request latency percentiles.', 'cases' => []];
$check = static function ($response, int $status, string $body): void {
    if ($response->getStatusCode() !== $status || (string) $response->getBody() !== $body) {
        throw new RuntimeException('Benchmark response verification failed');
    }
};
try {
    foreach ([10, 100, 1000] as $count) {
        $directory = $root . '/' . $count;
        mkdir($directory);
        for ($i = 0; $i < $count; $i++) {
            $relative = $i % 2 === 0 ? 'r' . $i . '.get.php' : 'r' . $i . '/[id].get.php';
            $file = $directory . '/' . $relative;
            if (!is_dir(dirname($file))) { mkdir(dirname($file)); }
            file_put_contents($file, '<?php return static fn($r) => new \\Nyholm\\Psr7\\Response(200, [], "fixture");');
        }
        $discovery = new RouteMap();
        $map = $discovery->discover($directory);
        if (count($map['static']) + count($map['dynamic']) !== $count) {
            throw new RuntimeException('Incorrect benchmark route count');
        }
        $cacheFile = $root . '/map-' . $count . '.php';
        $cache = new RouteCache($directory, $cacheFile, true, 3600);
        $cache->get();
        $router = new Router($directory, $cache);
        $static = new ServerRequest('GET', '/r' . ($count - 2));
        $dynamic = new ServerRequest('GET', '/r' . ($count - 1) . '/42');
        $missing = new ServerRequest('GET', '/missing');
        $cases = [];
        $cases['discovery'] = $benchmark->measure(static function () use ($discovery, $directory, $count): void {
            $map = $discovery->discover($directory);
            if (count($map['static']) + count($map['dynamic']) !== $count) { throw new RuntimeException('Incomplete discovery'); }
        }, 1);
        $cases['cached_request_startup'] = $benchmark->measure(static function () use ($directory, $cacheFile, $static, $check): void {
            $router = new Router($directory, new RouteCache($directory, $cacheFile, true, 3600));
            $check($router->handle($static), 200, 'fixture');
        }, 20);
        foreach (['static' => [$static, 200, 'fixture'], 'dynamic' => [$dynamic, 200, 'fixture'], '404' => [$missing, 404, '404 Not Found']] as $name => [$request, $status, $body]) {
            $cases['repeated_' . $name] = $benchmark->measure(static fn() => $check($router->handle($request), $status, $body), 200);
        }
        $viewDirectory = $directory . '/Views';
        mkdir($viewDirectory);
        foreach (['_header' => 'header', 'home' => 'body', '_footer' => 'footer'] as $name => $body) {
            file_put_contents($viewDirectory . '/' . $name . '.php', $body);
        }
        file_put_contents($directory . '/_middleware.php', '<?php return [new \\CorianderCore\\Core\\Security\\SecurityHeadersMiddleware()];');
        file_put_contents($directory . '/r' . ($count - 2) . '.get.php', '<?php return static fn($r) => (new \\CorianderCore\\Core\\Router\\ViewRenderer(' . var_export($viewDirectory, true) . '))->response("home");');
        if (function_exists('opcache_invalidate')) { opcache_invalidate($directory . '/r' . ($count - 2) . '.get.php', true); }
        $cases['middleware_and_layout'] = $benchmark->measure(static function () use ($router, $static, $check): void {
            $response = $router->handle($static);
            $check($response, 200, 'headerbodyfooter');
            if ($response->getHeaderLine('X-Content-Type-Options') !== 'nosniff') { throw new RuntimeException('Middleware did not run'); }
        }, 200);
        $results['cases'][(string) $count] = $cases;
    }
    $fixture = PROJECT_ROOT . '/CorianderCore/Tests/fixtures/http';
    $server = new HttpServer($fixture, $fixture . '/router.php');
    try {
        $results['http'] = $benchmark->measure(static function () use ($server): void {
            $response = $server->request('GET', '/teams/5/users/42');
            if ($response['status'] !== 200 || json_decode($response['body'], true) !== ['team' => '5', 'id' => '42', 'query' => []]) {
                throw new RuntimeException('HTTP benchmark response verification failed');
            }
        }, 20);
    } finally { $server->stop(); }
    $json = json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (isset($argv[1])) {
        if (file_put_contents($argv[1], $json . "\n") === false) { throw new RuntimeException('Cannot save benchmark results'); }
        echo 'Saved verified benchmark results to ' . $argv[1] . PHP_EOL;
    } else { echo $json . PHP_EOL; }
} finally {
    // This script only removes its own, verified temporary fixture tree.
    $resolved = str_replace('\\', '/', realpath($root));
    $expected = str_replace('\\', '/', realpath(PROJECT_ROOT . '/CorianderCore/Tests')) . '/_tmp_benchmark_';
    if (!str_starts_with($resolved, $expected)) { throw new RuntimeException('Unexpected benchmark cleanup path'); }
    $remove = static function (string $path) use (&$remove): void {
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $file = $path . '/' . $entry;
            if (is_dir($file) && !is_link($file)) { $remove($file); } else { unlink($file); }
        }
        rmdir($path);
    };
    $remove($root);
}
