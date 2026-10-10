<?php
declare(strict_types=1);
namespace CorianderCore\Core\Console\Commands\Make\Route;

use CorianderCore\Core\Console\{CommandExitCode, ConsoleOutput};
use CorianderCore\Core\Router\{RouteMap, SafePath};

class MakeRoute
{
    public function __construct(private string $basePath = PROJECT_ROOT . '/src/Routes') {}

    public function execute(array $args): int
    {
        if ($args === []) {
            ConsoleOutput::print('Please specify a route file name.');
            return CommandExitCode::INVALID_USAGE;
        }
        $name = SafePath::normalizeRelativePath($args[0]);
        if ($name === null) {
            ConsoleOutput::print('Invalid route file name.');
            return CommandExitCode::INVALID_USAGE;
        }
        $name = preg_replace('/\.php$/', '', $name);
        if (!in_array(pathinfo($name, PATHINFO_EXTENSION), RouteMap::METHODS, true)) {
            $name .= '.get';
        }
        $file = rtrim($this->basePath, '/\\') . '/' . $name . '.php';
        if (file_exists($file)) {
            ConsoleOutput::print('Route file already exists.');
            return CommandExitCode::FAILURE;
        }
        try {
            (new RouteMap())->validateNewRoute($this->basePath, $name . '.php');
            $file = SafePath::destination(rtrim($this->basePath, '/\\'), $name . '.php');
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0755, true)) {
                throw new \RuntimeException('Cannot create route directory');
            }
        } catch (\InvalidArgumentException $e) {
            ConsoleOutput::print($e->getMessage());
            return CommandExitCode::INVALID_USAGE;
        } catch (\RuntimeException $e) {
            ConsoleOutput::print($e->getMessage());
            return CommandExitCode::FAILURE;
        }
        $template = file_get_contents(PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/Route/templates/route.php');
        if ($template === false || file_put_contents($file, $template) === false) {
            ConsoleOutput::print('Failed to write route file.');
            return CommandExitCode::FAILURE;
        }
        ConsoleOutput::print("Success: {$name}.php is discovered automatically.");
        return CommandExitCode::SUCCESS;
    }
}
