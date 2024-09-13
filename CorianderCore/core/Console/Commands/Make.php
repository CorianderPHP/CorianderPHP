<?php

namespace CorianderCore\Console\Commands;

/**
 * Class responsible for handling make-related commands such as 'make:view'.
 * It delegates the specific subcommands (like view creation) to their respective handlers.
 * This class serves as the central point for managing resource creation commands in the CorianderPHP framework.
 */
class Make
{
    /**
     * List of valid subcommands that the 'make' command supports.
     * Each subcommand corresponds to a specific resource creation action (e.g., view).
     *
     * @var array
     */
    protected $validSubcommands = [
        'view',
        // Additional subcommands can be added here, such as 'controller', 'model', etc.
    ];

    /**
     * Instance of the subcommand handler (e.g., MakeView).
     *
     * @var object|null
     */
    protected $makeViewInstance;

    /**
     * Constructor for the Make class.
     * Accepts optional instances for the subcommand handlers (e.g., MakeView), allowing for dependency injection during testing.
     *
     * @param object|null $makeView Optional MakeView instance for testing or dependency injection.
     */
    public function __construct($makeView = null)
    {
        // Assign the provided MakeView instance or create a default one if not provided
        $this->makeViewInstance = $makeView ?: new \CorianderCore\Console\Commands\View\MakeView();
    }

    /**
     * Executes the appropriate subcommand based on user input.
     * 
     * The command accepts a subcommand as the first argument (e.g., 'view') and delegates
     * the execution to the corresponding handler. Additional arguments are passed to the
     * subcommand handler for further processing.
     *
     * Example:
     * - 'php coriander make:view home' will create a view named 'home' using the MakeView class.
     *
     * @param array $args The arguments passed to the make command, including the subcommand and resource name.
     */
    public function execute(array $args)
    {
        // Ensure the command has at least one argument (the subcommand)
        if (empty($args) || !isset($args[0])) {
            echo "Error: Invalid make command. Use 'make:view', 'make:controller', etc." . PHP_EOL;
            return;
        }

        // Extract the subcommand (e.g., 'view')
        $subcommand = strtolower($args[0]);

        // Verify if the provided subcommand is valid
        if (!in_array($subcommand, $this->validSubcommands)) {
            // Display an error message and list valid subcommands if the subcommand is invalid
            echo "Error: Unknown make command '{$subcommand}'. Valid commands are: " 
                 . implode(', ', $this->validSubcommands) . '.' . PHP_EOL;
            return;
        }

        // Extract the remaining arguments, which are specific to the resource (e.g., view name)
        $resourceArgs = array_slice($args, 1);

        // Delegate the execution based on the subcommand type
        switch ($subcommand) {
            case 'view':
                $this->makeView($resourceArgs); // Delegate to the MakeView handler
                break;

            // Additional cases for other subcommands (e.g., make:controller) can be added here

            default:
                // This fallback case is unlikely to be triggered due to the earlier validation,
                // but serves as a safety net to catch any unforeseen issues.
                echo "Error: Unknown subcommand '{$subcommand}'." . PHP_EOL;
        }
    }

    /**
     * Handles the creation of a view by delegating to the MakeView class.
     * 
     * This method checks for a valid view name and then calls the MakeView class
     * to handle the actual view creation process.
     *
     * @param array $args The arguments for creating the view (e.g., the name of the view).
     */
    protected function makeView(array $args)
    {
        // Ensure a view name is provided
        if (empty($args)) {
            echo "Error: Please specify a view name, e.g., 'make:view home'." . PHP_EOL;
            return;
        }

        // Delegate the view creation task to the MakeView class
        $this->makeViewInstance->execute($args);
    }
}
