<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\ConsoleOutput;
use CorianderCore\Core\Router\Services\ControllerCacheService;

/**
 * Handles cache related console commands.
 */
class Cache
{
    /**
     * Supported subcommands.
     *
     * @var array<int, string>
     */
    protected array $validSubcommands = [
        'controllers',
        'all',
        'clear'
    ];

    private ControllerCacheService $controllerCacheService;

    public function __construct()
    {
        $this->controllerCacheService = ControllerCacheService::getInstance();
    }

    /**
     * Execute the cache command.
     */
    public function execute(array $args): void
    {
        if (empty($args) || !isset($args[0])) {
            $this->listCommands();
            return;
        }

        $subcommand = strtolower($args[0]);
        if (!in_array($subcommand, $this->validSubcommands, true)) {
            ConsoleOutput::print("&4[Error]&7 Unknown cache command: cache:{$subcommand}\n");
            $this->listCommands();
            return;
        }

        $resourceArgs = array_slice($args, 1);
        switch ($subcommand) {
            case 'controllers':
                $this->cacheControllers($resourceArgs);
                break;
            case 'all':
                $this->cacheAll($resourceArgs);
                break;
            case 'clear':
                $this->clearCache($resourceArgs);
                break;
        }
    }

    protected function cacheControllers(array $args): void
    {
        $this->controllerCacheService->build();
        ConsoleOutput::print("&2[Success]&7 Controller cache generated.");
    }

    protected function cacheAll(array $args): void
    {
        $this->controllerCacheService->build();
        ConsoleOutput::print("&2[Success]&7 All caches generated.");
    }

    protected function clearCache(array $args): void
    {
        $this->controllerCacheService->clear();
        ConsoleOutput::print("&2[Success]&7 Cache cleared.");
    }

    protected function listCommands(): void
    {
        ConsoleOutput::print("Available cache commands:");
        foreach ($this->validSubcommands as $cmd) {
            ConsoleOutput::print("| - cache:{$cmd}");
        }
    }
}
