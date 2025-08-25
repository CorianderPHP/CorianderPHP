<?php

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\ConsoleOutput;

/**
 * Benchmark is responsible for handling benchmark-related commands such as 'benchmark:router'.
 * It delegates specific subcommands (like router benchmarking) to their respective handlers.
 * This class serves as the central point for managing benchmark commands in the CorianderPHP framework.
 */
class Benchmark
{
    /**
     * List of valid subcommands that the 'benchmark' command supports.
     * Each subcommand corresponds to a specific resource benchmark action (e.g., router).
     *
     * @var array
     */
    protected $validSubcommands = [
        'router'
    ];

    /**
     * Instances of the subcommand handlers (e.g., BenchmarkRouter).
     *
     * @var object|null
     */
    protected $benchmarkRouterInstance;
    /**
     * Constructor for the Benchmark class.
     */
    public function __construct()
    {
        $this->benchmarkRouterInstance = new \CorianderCore\Core\Console\Commands\Benchmark\BenchmarkRouter();
    }

    /**
     * Executes the appropriate subcommand based on user input.
     * 
     * The command accepts a subcommand as the first argument (e.g., 'router') and delegates
     * the execution to the corresponding handler. Additional arguments are passed to the
     * subcommand handler for further processing.
     *
     * Example:
     * - 'php coriander benchmark:router' will benchmark the router performance.
     *
     * @param array $args The arguments passed to the benchmark command, including the subcommand and resource name.
     */
    public function execute(array $args)
    {
        // Ensure the command has at least one argument (the subcommand)
        if (empty($args) || !isset($args[0])) {
            $this->listCommands();
            return;
        }

        // Extract the subcommand (e.g. 'router')
        $subcommand = strtolower($args[0]);

        // Verify if the provided subcommand is valid
        if (!in_array($subcommand, $this->validSubcommands)) {
            // Display an error message and list valid subcommands if the subcommand is invalid
            ConsoleOutput::print("&4[Error]&7 Unknown benchmark command: benchmark:{$subcommand}\n");
            $this->listCommands();
            return;
        }

        // Extract the remaining arguments, which are specific to the resource (e.g., view or controller name)
        $resourceArgs = array_slice($args, 1);

        // Delegate the execution based on the subcommand type
        switch ($subcommand) {
            case 'router':
                $this->benchmarkRouter($resourceArgs); // Delegate to the BenchmarkRouter handler
                break;

            default:
                // This fallback case is unlikely to be triggered due to the earlier validation,
                // but serves as a safety net to catch any unforeseen issues.
                ConsoleOutput::print("&4[Error]&7 Unknown benchmark command: benchmark:{$subcommand}\n");
        }
    }

    /**
     * Handles the benchmarking of the router by delegating to the BenchmarkRouter class.
     * 
     * This method initiates the router benchmarking process.
     *
     * @param array $args The arguments for benchmarking the router.
     */
    protected function benchmarkRouter(array $args)
    {
        // Delegate the router benchmarking task to the BenchmarkRouter class
        $this->benchmarkRouterInstance->execute($args);
    }

    /**
     * Lists all available benchmark: commands.
     */
    protected function listCommands()
    {
        ConsoleOutput::print("Available benchmark commands:");

        foreach ($this->validSubcommands as $cmd) {
            ConsoleOutput::print("| - benchmark:{$cmd}");
        }
    }
}
