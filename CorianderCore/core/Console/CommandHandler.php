<?php

namespace CorianderCore\Core\Console;

class CommandHandler
{
    /**
     * Available commands and their corresponding handler classes.
     *
     * @var array
     */
    protected $commands = [
        'hello' => \CorianderCore\Core\Console\Commands\Hello::class,
        'nodejs' => \CorianderCore\Core\Console\Commands\NodeJS::class,
        'make' => \CorianderCore\Core\Console\Commands\Make::class,
        'benchmark' => \CorianderCore\Core\Console\Commands\Benchmark::class,
        'cache' => \CorianderCore\Core\Console\Commands\Cache::class,
    ];

    /**
     * Handles the execution of the given command.
     *
     * @param string $command The command name to execute
     * @param array $args The arguments passed to the command
     * @throws \Exception If the command does not exist or the command class lacks an 'execute' method.
     */
    public function handle(string $command, array $args)
    {
        ConsoleOutput::hr();
        // Check if the command contains a colon (e.g., make:view)
        $splitCommand = explode(':', $command);

        // If it's a subcommand (e.g., make:view), treat it as "make" with "view" as the subcommand
        $mainCommand = $splitCommand[0];
        $subCommand = $splitCommand[1] ?? null; // Get subcommand if present

        // If 'help' is requested or no command is provided, display the list of commands
        if ($mainCommand === 'help' || !$mainCommand) {
            $this->listCommands();
            ConsoleOutput::hr();
            return;
        }

        // Check if the main command exists
        if (!isset($this->commands[$mainCommand])) {
            ConsoleOutput::print("&4[Error]&7 Unknown command: {$mainCommand}\n");
            $this->listCommands();
            ConsoleOutput::hr();
            return;
        }

        $commandClass = $this->commands[$mainCommand];

        // Check if the corresponding class exists for the main command
        if (!class_exists($commandClass)) {
            throw new \Exception("Command class {$commandClass} not found.");
        }

        // Instantiate the main command class
        $commandInstance = new $commandClass();

        // If it's a subcommand (e.g., view for make:view), pass it as the first argument
        if ($subCommand) {
            array_unshift($args, $subCommand);
        }

        // Ensure the main command class has an 'execute' method
        if (!method_exists($commandInstance, 'execute')) {
            throw new \Exception("Command {$mainCommand} does not have an execute method.");
        }

        // Execute the main command with the provided arguments (subcommand included)
        $commandInstance->execute($args);
        ConsoleOutput::hr();
    }

    /**
     * Lists all available commands, including 'help'.
     */
    protected function listCommands()
    {
        ConsoleOutput::print("Available commands:");

        // Always include 'help' as an available command
        ConsoleOutput::print("| - help");

        // List all commands from the $commands array
        foreach ($this->commands as $cmd => $class) {
            ConsoleOutput::print("| - {$cmd}");
        }
    }
}
