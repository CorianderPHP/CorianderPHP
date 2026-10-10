<?php
declare(strict_types=1);
namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\{CommandExitCode, ConsoleOutput};
use CorianderCore\Core\Router\RouteCache;

class Cache
{
    public function execute(array $args): int
    {
        if ($args === []) {
            ConsoleOutput::print('Available cache commands: cache clear (automatic rebuilding on request).');
            return CommandExitCode::SUCCESS;
        }
        if ($args[0] !== 'clear') {
            ConsoleOutput::print('Unknown cache command. Use cache clear.');
            return CommandExitCode::UNKNOWN_COMMAND;
        }
        (new RouteCache())->clear();
        ConsoleOutput::print('Success: Route cache cleared.');
        return CommandExitCode::SUCCESS;
    }
}
