<?php
declare(strict_types=1);
namespace CorianderCore\Core\Console\Commands\Make\View;

use CorianderCore\Core\Console\{CommandExitCode, ConsoleOutput};
use CorianderCore\Core\Router\{RouteMap, SafePath};

class MakeView
{
    public function __construct(private string $viewPath = PROJECT_ROOT . '/src/Views') {}

    public function execute(array $args): int
    {
        if ($args === []) {
            ConsoleOutput::print('Error: Please specify a view name.');
            return CommandExitCode::INVALID_USAGE;
        }
        $name = SafePath::normalizeRelativePath($args[0]);
        if ($name === null) {
            ConsoleOutput::print('Error: Invalid view name.');
            return CommandExitCode::INVALID_USAGE;
        }
        $name = preg_replace('/\.php$/', '', $name);
        $explicit = str_ends_with($name, '.get');
        if (in_array(pathinfo($name, PATHINFO_EXTENSION), array_diff(RouteMap::METHODS, ['get']), true)) {
            ConsoleOutput::print('Error: make:view generates GET pages; use make:route for other methods.');
            return CommandExitCode::INVALID_USAGE;
        }
        if ($explicit) {
            $name = substr($name, 0, -4);
        }
        $segments = array_map(static fn(string $segment): string => str_starts_with($segment, '[')
            ? $segment : strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $segment)), explode('/', $name));
        $name = implode('/', $segments);
        $relative = ($explicit || basename($name) === 'index' ? $name : $name . '/index') . '.get.php';
        $file = rtrim($this->viewPath, '/\\') . '/' . $relative;
        if (file_exists($file)) {
            ConsoleOutput::print("Error: View '{$name}' already exists.");
            return CommandExitCode::FAILURE;
        }
        try {
            (new RouteMap())->validateNewRoute($this->viewPath, $relative);
            $file = SafePath::destination(rtrim($this->viewPath, '/\\'), $relative);
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0755, true)) {
                throw new \RuntimeException('Cannot create view directory');
            }
        } catch (\InvalidArgumentException $e) {
            ConsoleOutput::print('Error: ' . $e->getMessage());
            return CommandExitCode::INVALID_USAGE;
        } catch (\RuntimeException $e) {
            ConsoleOutput::print('Error: ' . $e->getMessage());
            return CommandExitCode::FAILURE;
        }
        $template = file_get_contents(PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/View/templates/view.php');
        if ($template === false || file_put_contents($file, str_replace('{{viewName}}', $name, $template)) === false) {
            ConsoleOutput::print('Error: Failed to write view file.');
            return CommandExitCode::FAILURE;
        }
        ConsoleOutput::print("Success: View '{$name}' created successfully at '{$file}'.");
        return CommandExitCode::SUCCESS;
    }
}
