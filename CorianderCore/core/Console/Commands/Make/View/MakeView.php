<?php
declare(strict_types=1);
namespace CorianderCore\Core\Console\Commands\Make\View;

use CorianderCore\Core\Console\{CommandExitCode, ConsoleOutput};
use CorianderCore\Core\Router\SafePath;

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
        if ($name === null || !preg_match('#^[A-Za-z0-9][A-Za-z0-9/-]*$#', $name)) {
            ConsoleOutput::print('Error: Invalid view name.');
            return CommandExitCode::INVALID_USAGE;
        }
        $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
        $file = rtrim($this->viewPath, '/\\') . '/' . $name . '.php';
        if (file_exists($file)) {
            ConsoleOutput::print("Error: View '{$name}' already exists.");
            return CommandExitCode::FAILURE;
        }
        try {
            $file = SafePath::destination(rtrim($this->viewPath, '/\\'), $name . '.php');
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0755, true)) {
                throw new \RuntimeException('Cannot create view directory');
            }
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
