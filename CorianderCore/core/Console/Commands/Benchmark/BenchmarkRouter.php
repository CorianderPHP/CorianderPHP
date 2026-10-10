<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Benchmark;

use CorianderCore\Core\Benchmark\BenchmarkHandler;
use CorianderCore\Core\Console\CommandExitCode;
use CorianderCore\Core\Console\ConsoleOutput;
use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

class BenchmarkRouter
{
    public function execute(array $args): int
    {
        $route = $args[0] ?? 'home';
        $iterations = isset($args[1]) ? filter_var($args[1], FILTER_VALIDATE_INT) : 1000;
        if ($iterations === false || $iterations < 1) {
            ConsoleOutput::print('Iterations must be a positive integer.');
            return CommandExitCode::INVALID_USAGE;
        }
        $router = new Router();
        $request = new ServerRequest('GET', '/' . ltrim($route, '/'));
        $expected = $router->handle($request);
        if ($expected->getStatusCode() >= 400) {
            ConsoleOutput::print("Route '{$route}' returned HTTP " . $expected->getStatusCode() . '; benchmark refused.');
            return CommandExitCode::INVALID_USAGE;
        }
        $body = (string) $expected->getBody();
        $operation = static function () use ($router, $request, $expected, $body): void {
            $response = $router->handle($request);
            if ($response->getStatusCode() !== $expected->getStatusCode() || (string) $response->getBody() !== $body) {
                throw new \RuntimeException('Route response changed during the benchmark. Use a deterministic fixture.');
            }
        };
        $results = (new BenchmarkHandler())->measure($operation, $iterations);
        ConsoleOutput::print('CLI dispatch benchmark (includes status/body verification; excludes web-server startup):');
        ConsoleOutput::print(json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return CommandExitCode::SUCCESS;
    }
}
