<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Make\Route;

use CorianderCore\Core\Console\CommandExitCode;
use CorianderCore\Core\Console\ConsoleOutput;

class MakeRoute
{
    private string $basePath;

    private string $templatesPath;

    public function __construct(string $basePath = PROJECT_ROOT . '/src/Routes/')
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/') . '/';
        $this->templatesPath = PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/Route/templates';
    }

    /**
     * @param array<int, string> $args
     */
    public function execute(array $args): int
    {
        if ($args === []) {
            ConsoleOutput::print("&4[Error]&7 Please specify a route file name.");
            return CommandExitCode::INVALID_USAGE;
        }

        $routeName = $this->normalizeRouteName($args[0]);
        if ($routeName === '') {
            ConsoleOutput::print("&4[Error]&7 Invalid route file name.");
            return CommandExitCode::INVALID_USAGE;
        }

        $routePath = $this->basePath . $routeName . '.php';
        if (file_exists($routePath)) {
            ConsoleOutput::print("&4[Error]&7 Route file '{$routeName}.php' already exists at '{$routePath}'.");
            return CommandExitCode::FAILURE;
        }

        try {
            $this->ensureDirectoryExists(dirname($routePath));
            $this->createFileFromTemplate($routePath, $routeName);
        } catch (\Throwable $exception) {
            ConsoleOutput::print("&4[Error]&7 Failed to create route file: " . $exception->getMessage());
            return CommandExitCode::FAILURE;
        }

        ConsoleOutput::print("&2[Success]&7 Route file '{$routeName}.php' created successfully at '{$routePath}'.");
        ConsoleOutput::print("&7Register it from public/routes.php with: &8(require PROJECT_ROOT . '/src/Routes/{$routeName}.php')(\$router);");

        return CommandExitCode::SUCCESS;
    }

    private function normalizeRouteName(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        if ($name === '' || str_contains($name, '..')) {
            return '';
        }

        $segments = array_filter(explode('/', $name), static fn(string $segment): bool => $segment !== '');
        $normalized = [];

        foreach ($segments as $segment) {
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
                return '';
            }

            $normalized[] = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $segment));
        }

        return implode('/', $normalized);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Failed to create directory: '{$directory}'");
        }
    }

    private function createFileFromTemplate(string $destinationFile, string $routeName): void
    {
        $templatePath = $this->templatesPath . '/route.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException("Template 'route.php' not found.");
        }

        $content = file_get_contents($templatePath);
        if (!is_string($content)) {
            throw new \RuntimeException("Unable to read route template.");
        }

        $content = str_replace('{{routeName}}', $routeName, $content);

        if (file_put_contents($destinationFile, $content) === false) {
            throw new \RuntimeException("Failed to write route file '{$destinationFile}'.");
        }
    }
}
