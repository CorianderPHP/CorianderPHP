<?php

namespace CorianderCore\Console;

class CommandHandler
{
    /**
     * Available commands and their corresponding handler classes.
     *
     * An associative array mapping command names (e.g., 'hello', 'nodejs')
     * to their respective handler classes, which must implement an 'execute' method.
     *
     * @var array
     */
    protected $commands = [
        'hello' => \CorianderCore\Console\Commands\Hello::class,
        'nodejs' => \CorianderCore\Console\Commands\NodeJS::class,
        // Add other commands here
    ];

    /**
     * Handles the execution of the given command.
     *
     * This method checks if the given command exists in the $commands array, 
     * validates that the associated command class exists, and ensures it has an 'execute' method.
     * If the command is valid, it is executed with the provided arguments. If 'help' is requested,
     * or the command is not found, the list of available commands is shown.
     *
     * @param string $command The command name to execute
     * @param array $args The arguments passed to the command
     * @throws \Exception If the command does not exist or the command class lacks an 'execute' method.
     */
    public function handle(string $command, array $args)
    {
        // If 'help' is requested or no command is provided, display the list of commands
        if ($command === 'help' || !$command) {
            $this->listCommands();
            return;
        }

        // Check if the command exists in the list of available commands
        if (!isset($this->commands[$command])) {
            echo "Unknown command: {$command}" . PHP_EOL;
            $this->listCommands();
            return;
        }

        $commandClass = $this->commands[$command];

        // Check if the corresponding class exists for the command
        if (!class_exists($commandClass)) {
            throw new \Exception("Command class {$commandClass} not found.");
        }

        // Instantiate the command class
        $commandInstance = new $commandClass();

        // Ensure the command class has an 'execute' method
        if (!method_exists($commandInstance, 'execute')) {
            throw new \Exception("Command {$command} does not have an execute method.");
        }

        // Execute the command with the provided arguments
        $commandInstance->execute($args);
    }

    /**
     * Lists all available commands, including 'help'.
     *
     * This method outputs a list of all available commands in the $commands array,
     * as well as a default 'help' command that provides assistance to the user.
     */
    protected function listCommands()
    {
        echo "Available commands:" . PHP_EOL;

        // Always include 'help' as an available command
        echo "  - help" . PHP_EOL;

        // List all commands from the $commands array
        foreach ($this->commands as $cmd => $class) {
            echo "  - {$cmd}" . PHP_EOL;
        }
    }
}
