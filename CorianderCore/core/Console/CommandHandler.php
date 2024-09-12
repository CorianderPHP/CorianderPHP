<?php

namespace CorianderCore\Console;

class CommandHandler
{
    /**
     * Available commands and their corresponding handler classes.
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
     * @param string $command The command name to execute
     * @param array $args The arguments passed to the command
     * @throws \Exception If the command is not valid or does not have an execute method.
     */
    public function handle(string $command, array $args)
    {
        // If 'help' is requested or no command is provided, show the command list
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

        // Check if the class for the command exists
        if (!class_exists($commandClass)) {
            throw new \Exception("Command class {$commandClass} not found.");
        }

        $commandInstance = new $commandClass();

        // Ensure the command class has an 'execute' method
        if (!method_exists($commandInstance, 'execute')) {
            throw new \Exception("Command {$command} does not have an execute method.");
        }

        // Execute the command
        $commandInstance->execute($args);
    }

    /**
     * Lists all available commands, including 'help'.
     */
    protected function listCommands()
    {
        echo "Available commands:" . PHP_EOL;

        // Always include 'help' as an available command
        echo "  - help" . PHP_EOL;

        // List commands from the $commands array
        foreach ($this->commands as $cmd => $class) {
            echo "  - {$cmd}" . PHP_EOL;
        }
    }
}
