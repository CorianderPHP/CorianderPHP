<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\{CommandExitCode, ConsoleOutput};
use CorianderCore\Core\Router\RouteMap;

final class Routes
{
    public function __construct(private string $directory = PROJECT_ROOT . '/src/Routes') {}

    /** @param list<string> $args */
    public function execute(array $args): int
    {
        if ($args === []) {
            ConsoleOutput::print('Usage: php coriander routes:list');
            return CommandExitCode::SUCCESS;
        }
        if ($args !== ['list']) {
            ConsoleOutput::print('[Error] Usage: php coriander routes:list');
            return CommandExitCode::INVALID_USAGE;
        }
        try {
            $map = (new RouteMap())->discover($this->directory);
        } catch (\Throwable $exception) {
            ConsoleOutput::print('[Error] ' . $exception->getMessage());
            return CommandExitCode::FAILURE;
        }
        $routes = array_merge(array_values($map['static']), $map['dynamic']);
        usort($routes, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
        if ($routes === []) {
            ConsoleOutput::print('No routes found.');
            return CommandExitCode::SUCCESS;
        }
        $rows = [['METHOD', 'PATH', 'HANDLER', 'MIDDLEWARE (root first)']];
        foreach ($routes as $route) {
            $methods = $route['methods'];
            $headFallback = !isset($methods['HEAD']) && isset($methods['GET']);
            if ($headFallback) {
                $methods['HEAD'] = $methods['GET'];
            }
            ksort($methods);
            foreach ($methods as $method => $target) {
                $rows[] = [$method === 'HEAD' && $headFallback ? 'HEAD (GET)' : $method,
                    '/' . $route['path'], $target['file'], implode(' -> ', $target['middleware']) ?: '-'];
            }
        }
        $widths = [];
        for ($column = 0; $column < 3; $column++) {
            $widths[$column] = max(array_map('strlen', array_column($rows, $column)));
        }
        foreach ($rows as $row) {
            ConsoleOutput::print(str_pad($row[0], $widths[0]) . '  ' . str_pad($row[1], $widths[1])
                . '  ' . str_pad($row[2], $widths[2]) . '  ' . $row[3]);
        }
        return CommandExitCode::SUCCESS;
    }
}
