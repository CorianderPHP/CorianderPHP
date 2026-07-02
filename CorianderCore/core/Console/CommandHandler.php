<?php
declare(strict_types=1);

/*
 * CommandHandler orchestrates CLI command execution by mapping command names
 * to handler classes and delegating argument processing to each command.
 */

namespace CorianderCore\Core\Console;

/**
 * Routes console commands to their corresponding handler classes and manages execution.
 *
 * Maintains the registry of available commands, dispatches subcommands, and
 * provides help output when requested or when an unknown command is encountered.
 */
class CommandHandler
{
    /**
     * Available commands and their corresponding handler classes.
     *
     * @var array<string, class-string>
     */
    protected array $commands = [
        'hello' => \CorianderCore\Core\Console\Commands\Hello::class,
        'nodejs' => \CorianderCore\Core\Console\Commands\NodeJS::class,
        'make' => \CorianderCore\Core\Console\Commands\Make::class,
        'benchmark' => \CorianderCore\Core\Console\Commands\Benchmark::class,
        'cache' => \CorianderCore\Core\Console\Commands\Cache::class,
        'version' => \CorianderCore\Core\Console\Commands\Version::class,
        'update' => \CorianderCore\Core\Console\Commands\Update::class,
        'migrate' => \CorianderCore\Core\Console\Commands\Migrate::class,
    ];

    /**
     * Handles the execution of the given command.
     *
     * @param string $command The command name to execute
     * @param array $args The arguments passed to the command
     * @throws \Exception If the command does not exist or the command class lacks an 'execute' method.
     * @return int Process exit code.
     */
    public function handle(string $command, array $args): int
    {
        ConsoleOutput::hr();
        $splitCommand = explode(':', $command);

        $mainCommand = $splitCommand[0];
        $subCommand = $splitCommand[1] ?? null;

        if ($mainCommand === 'help' || !$mainCommand) {
            $this->listCommands();
            ConsoleOutput::hr();
            return CommandExitCode::SUCCESS;
        }

        if (!isset($this->commands[$mainCommand])) {
            ConsoleOutput::print("&4[Error]&7 Unknown command: {$mainCommand}\n");
            $this->listCommands();
            ConsoleOutput::hr();
            return CommandExitCode::UNKNOWN_COMMAND;
        }

        $commandClass = $this->commands[$mainCommand];

        if (!class_exists($commandClass)) {
            throw new \Exception("Command class {$commandClass} not found.");
        }

        $commandInstance = new $commandClass();

        if ($subCommand) {
            array_unshift($args, $subCommand);
        }

        if (!method_exists($commandInstance, 'execute')) {
            throw new \Exception("Command {$mainCommand} does not have an execute method.");
        }

        $result = $commandInstance->execute($args);
        ConsoleOutput::hr();

        return $this->normalizeExitCode($result);
    }

    /**
     * Lists all available commands, including 'help'.
     *
     * @return void
     */
    protected function listCommands(): void
    {
        ConsoleOutput::print("Available commands:");

        ConsoleOutput::print("| - help");

        foreach ($this->commands as $cmd => $class) {
            ConsoleOutput::print("| - {$cmd}");
        }
    }

    private function normalizeExitCode(mixed $result): int
    {
        if (is_int($result)) {
            return max(0, min(255, $result));
        }

        if (is_bool($result)) {
            return $result ? CommandExitCode::SUCCESS : CommandExitCode::FAILURE;
        }

        return CommandExitCode::SUCCESS;
    }
}




