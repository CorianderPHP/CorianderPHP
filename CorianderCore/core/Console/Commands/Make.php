<?php

namespace CorianderCore\Console\Commands;

/**
 * Class responsible for handling make-related commands such as 'make:view', 'make:controller'.
 * It delegates the specific subcommands (like view or controller creation) to their respective handlers.
 * This class serves as the central point for managing resource creation commands in the CorianderPHP framework.
 */
class Make
{
    /**
     * List of valid subcommands that the 'make' command supports.
     * Each subcommand corresponds to a specific resource creation action (e.g., view, controller).
     *
     * @var array
     */
    protected $validSubcommands = [
        'view',
        'controller'
    ];

    /**
     * Instances of the subcommand handlers (e.g., MakeView, MakeController).
     *
     * @var object|null
     */
    protected $makeViewInstance;
    protected $makeControllerInstance;

    /**
     * Constructor for the Make class.
     * Accepts optional instances for the subcommand handlers (e.g., MakeView, MakeController), 
     * allowing for dependency injection during testing.
     *
     * @param object|null $makeView Optional MakeView instance for testing or dependency injection.
     * @param object|null $makeController Optional MakeController instance for testing or dependency injection.
     */
    public function __construct($makeView = null, $makeController = null)
    {
        // Assign the provided instances or create default ones if not provided
        $this->makeViewInstance = $makeView ?: new \CorianderCore\Console\Commands\View\MakeView();
        $this->makeControllerInstance = $makeController ?: new \CorianderCore\Console\Commands\Controller\MakeController();
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
     * - 'php coriander make:controller User' will create a controller named 'UserController'.
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

        // Extract the subcommand (e.g., 'view' or 'controller')
        $subcommand = strtolower($args[0]);

        // Verify if the provided subcommand is valid
        if (!in_array($subcommand, $this->validSubcommands)) {
            // Display an error message and list valid subcommands if the subcommand is invalid
            echo "Error: Unknown make command '{$subcommand}'. Valid commands are: " 
                 . implode(', ', $this->validSubcommands) . '.' . PHP_EOL;
            return;
        }

        // Extract the remaining arguments, which are specific to the resource (e.g., view or controller name)
        $resourceArgs = array_slice($args, 1);

        // Delegate the execution based on the subcommand type
        switch ($subcommand) {
            case 'view':
                $this->makeView($resourceArgs); // Delegate to the MakeView handler
                break;

            case 'controller':
                $this->makeController($resourceArgs); // Delegate to the MakeController handler
                break;

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

    /**
     * Handles the creation of a controller by delegating to the MakeController class.
     * 
     * This method checks for a valid controller name and then calls the MakeController class
     * to handle the actual controller creation process.
     *
     * @param array $args The arguments for creating the controller (e.g., the name of the controller).
     */
    protected function makeController(array $args)
    {
        // Ensure a controller name is provided
        if (empty($args)) {
            echo "Error: Please specify a controller name, e.g., 'make:controller User'." . PHP_EOL;
            return;
        }

        // Delegate the controller creation task to the MakeController class
        $this->makeControllerInstance->execute($args);
    }
}
