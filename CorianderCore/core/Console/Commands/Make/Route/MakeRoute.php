<?php
declare(strict_types=1);
namespace CorianderCore\Core\Console\Commands\Make\Route;

use CorianderCore\Core\Console\{CommandExitCode, ConsoleOutput};
use CorianderCore\Core\Router\SafePath;

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
        $name = $name === null ? '' : preg_replace('/\.php$/', '', $name);
        if (!preg_match('/\.(get|post|put|patch|delete|head|options)$/', $name)) {
            $name .= '.get';
        }
        $path = preg_replace('/\.(get|post|put|patch|delete|head|options)$/', '', $name);
        foreach (explode('/', $path) as $segment) {
            if (!preg_match('/^(?:[A-Za-z0-9][A-Za-z0-9._-]*|\[[A-Za-z][A-Za-z0-9_]*\])$/', $segment)) {
                ConsoleOutput::print('Invalid route file name.');
                return CommandExitCode::INVALID_USAGE;
            }
        }
        $file = rtrim($this->basePath, '/\\') . '/' . $name . '.php';
        if (file_exists($file)) {
            ConsoleOutput::print('Route file already exists.');
            return CommandExitCode::FAILURE;
        }
        try {
            $file = SafePath::destination(rtrim($this->basePath, '/\\'), $name . '.php');
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0755, true)) {
                throw new \RuntimeException('Cannot create route directory');
            }
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
