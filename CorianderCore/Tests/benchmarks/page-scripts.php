<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/CorianderCore/autoload.php';

use CorianderCore\Core\Benchmark\BenchmarkHandler;
use CorianderCore\Core\Router\ViewRenderer;
use CorianderCore\Core\Support\PublicUrl;
use Nyholm\Psr7\ServerRequest;

$root = PROJECT_ROOT . '/CorianderCore/Tests/_tmp_scripts_benchmark_' . bin2hex(random_bytes(5));
mkdir($root . '/Views/admin', 0775, true);
file_put_contents($root . '/Views/admin/index.get.php', 'body');
$assets = $root . '/assets';
$request = new ServerRequest('GET', '/admin');
$benchmark = new BenchmarkHandler();
$report = ['description' => 'Verified renderer batch means in microseconds; not individual HTTP latency. Includes constructor, rendering and response validation. Process allocator peak excludes RSS, browser assets and shared OPcache.',
    'source_commit' => getenv('GITHUB_SHA') ?: null, 'cases' => []];
$measure = static function (string $expected) use ($root, $assets, $request, $benchmark): array {
    return $benchmark->measure(static function () use ($root, $assets, $request, $expected): void {
        $response = (new ViewRenderer($root . '/Views', $assets))->dispatch('admin/index.get.php', $request);
        if ($response->getStatusCode() !== 200 || (string) $response->getBody() !== $expected) {
            throw new RuntimeException('Script renderer response verification failed');
        }
    }, 200);
};
try {
    $report['cases']['missing_asset_directory'] = $measure('body');
    mkdir($assets . '/admin', 0775, true);
    $report['cases']['missing_bundles'] = $measure('body');
    $expected = 'body';
    foreach (['_script.js', 'admin/_script.js', 'admin/index.js'] as $name) {
        file_put_contents($assets . '/' . $name, 'console.log("fixture");');
        touch($assets . '/' . $name, 1700000000);
        $expected .= '<script type="module" src="' . htmlspecialchars(PublicUrl::asset('assets/js/pages/' . $name) . '?1700000000', ENT_QUOTES, 'UTF-8') . '"></script>';
    }
    $report['cases']['three_bundles'] = $measure($expected);
    for ($file = 0; $file < 500; $file++) {
        file_put_contents($assets . '/unrelated' . $file . '.js', 'console.log("unrelated");');
    }
    $report['cases']['three_bundles_with_500_unrelated_assets'] = $measure($expected);
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (isset($argv[1])) {
        if (file_put_contents($argv[1], $json) === false) { throw new RuntimeException('Cannot save script benchmark'); }
        echo 'Saved verified script benchmark to ' . $argv[1] . PHP_EOL;
    } else { echo $json; }
} finally {
    $resolved = str_replace('\\', '/', realpath($root));
    $prefix = str_replace('\\', '/', realpath(PROJECT_ROOT . '/CorianderCore/Tests')) . '/_tmp_scripts_benchmark_';
    if (!str_starts_with($resolved, $prefix)) { throw new RuntimeException('Unexpected benchmark cleanup path'); }
    $remove = static function (string $directory) use (&$remove): void {
        foreach (scandir($directory) as $name) {
            if ($name === '.' || $name === '..') { continue; }
            $file = $directory . '/' . $name;
            if (is_dir($file) && !is_link($file)) { $remove($file); } else { unlink($file); }
        }
        rmdir($directory);
    };
    $remove($root);
}
