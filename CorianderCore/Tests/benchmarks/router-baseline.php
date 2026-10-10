<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use CorianderCore\Core\Benchmark\BenchmarkHandler;
use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

$results = [];
foreach ([10, 100, 1000] as $count) {
    $router = new Router();
    for ($i = 0; $i < $count; $i++) {
        $router->get('/page-' . $i, static fn() => new Response(200, [], 'fixture'));
    }
    $router->get('/users/{id}', static fn($request) => new Response(200, [], $request->getAttribute('id')));
    foreach (['static' => ['/page-' . ($count - 1), 200, 'fixture'], 'dynamic' => ['/users/42', 200, '42'],
        'missing' => ['/missing', 404, '404 Not Found']] as $scenario => [$path, $status, $body]) {
        $request = new ServerRequest('GET', $path);
        $operation = static function () use ($router, $request, $status, $body): void {
            $response = $router->dispatch($request);
            if ($response->getStatusCode() !== $status || (string) $response->getBody() !== $body) {
                throw new RuntimeException('Incorrect benchmark fixture response.');
            }
        };
        $results[$count][$scenario] = (new BenchmarkHandler())->measure($operation);
    }
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
