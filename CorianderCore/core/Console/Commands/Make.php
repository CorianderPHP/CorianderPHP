<?php

namespace CorianderCore\Console\Commands;

use CorianderCore\Console\ConsoleOutput;

/**
 * Class responsible for handling make-related commands such as 'make:view', 'make:controller', and 'make:database'.
 * It delegates the specific subcommands (like view, controller, or database creation) to their respective handlers.
 * This class serves as the central point for managing resource creation commands in the CorianderPHP framework.
 */
class Make
{
    /**
     * List of valid subcommands that the 'make' command supports.
     * Each subcommand corresponds to a specific resource creation action (e.g., view, controller, database).
     *
     * @var array
     */
    protected $validSubcommands = [
        'view',
        'controller',
        'database'
    ];

    /**
     * Instances of the subcommand handlers (e.g., MakeView, MakeController, MakeDatabase).
     *
     * @var object|null
     */
    protected $makeViewInstance;
    protected $makeControllerInstance;
    protected $makeDatabaseInstance;

    /**
     * Constructor for the Make class.
     * Accepts optional instances for the subcommand handlers (e.g., MakeView, MakeController, MakeDatabase),
     * allowing for dependency injection during testing.
     *
     * @param object|null $makeView Optional MakeView instance for testing or dependency injection.
     * @param object|null $makeController Optional MakeController instance for testing or dependency injection.
     * @param object|null $makeDatabase Optional MakeDatabase instance for testing or dependency injection.
     */
    public function __construct($makeView = null, $makeController = null, $makeDatabase = null)
    {
        // Assign the provided instances or create default ones if not provided
        $this->makeViewInstance = $makeView ?: new \CorianderCore\Console\Commands\View\MakeView();
        $this->makeControllerInstance = $makeController ?: new \CorianderCore\Console\Commands\Controller\MakeController();
        $this->makeDatabaseInstance = $makeDatabase ?: new \CorianderCore\Console\Commands\Database\MakeDatabase();
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
     * - 'php coriander make:database' will initiate the process of database configuration.
     *
     * @param array $args The arguments passed to the make command, including the subcommand and resource name.
     */
    public function execute(array $args)
    {
        // Ensure the command has at least one argument (the subcommand)
        if (empty($args) || !isset($args[0])) {
            $this->listCommands();
            return;
        }

        // Extract the subcommand (e.g., 'view', 'controller', 'database')
        $subcommand = strtolower($args[0]);

        // Verify if the provided subcommand is valid
        if (!in_array($subcommand, $this->validSubcommands)) {
            // Display an error message and list valid subcommands if the subcommand is invalid
            ConsoleOutput::print("&4[Error]&7 Unknown make command: make:{$subcommand}\n");
            $this->listCommands();
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

            case 'database':
                $this->makeDatabase($resourceArgs); // Delegate to the MakeDatabase handler
                break;

            default:
                // This fallback case is unlikely to be triggered due to the earlier validation,
                // but serves as a safety net to catch any unforeseen issues.
                ConsoleOutput::print("&4[Error]&7 Unknown make command: make:{$subcommand}\n");
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
            ConsoleOutput::print("&4[Error]&7 Please specify a view name, e.g., 'make:view agenda'");
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
            ConsoleOutput::print("&4[Error]&7 Please specify a controller name, e.g., 'make:controller Agenda'.");
            return;
        }

        // Delegate the controller creation task to the MakeController class
        $this->makeControllerInstance->execute($args);
    }

    /**
     * Handles the creation of a database configuration by delegating to the MakeDatabase class.
     * 
     * This method calls the MakeDatabase class to initiate the process of database creation and configuration.
     *
     * @param array $args The arguments for creating or configuring the database.
     */
    protected function makeDatabase(array $args)
    {
        // Delegate the database creation task to the MakeDatabase class
        $this->makeDatabaseInstance->execute($args);
    }


    /**
     * Lists all available make: commands.
     */
    protected function listCommands()
    {
        ConsoleOutput::print("Available make commands:");

        foreach ($this->validSubcommands as $cmd) {
            ConsoleOutput::print("| - make:{$cmd}");
        }
    }
}
