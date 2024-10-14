<?php

namespace CorianderCore\Console\Commands\Make\Controller;

use CorianderCore\Console\ConsoleOutput;

/**
 * The MakeController class is responsible for generating new controller files
 * based on predefined templates. It ensures that controllers are created in
 * the appropriate directory following the framework's structure.
 */
class MakeController
{
    /**
     * @var string $templatesPath The path to the directory containing controller templates.
     */
    protected $templatesPath;

    /**
     * @var string $basePath The base path where controllers will be created.
     */
    protected $basePath;

    /**
     * Constructor for the MakeController class.
     * 
     * Initializes the path to the directory where controller templates are stored,
     * and sets the base path where controllers will be generated.
     * 
     * @param string $basePath The base path where controllers will be stored (default: PROJECT_ROOT . '/src/Controllers/').
     */
    public function __construct(string $basePath = PROJECT_ROOT . '/src/Controllers/')
    {
        // Set the path to the base controller directory and templates directory.
        $this->basePath = $basePath;
        $this->templatesPath = PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/Controller/templates';
    }

    /**
     * Executes the controller creation process.
     * 
     * This method handles the creation of a new controller by:
     * - Verifying if a controller name is provided.
     * - Ensuring the controller name follows the proper naming conventions.
     * - Checking if the controller already exists.
     * - Creating the necessary file using a template.
     *
     * @param array $args The arguments passed to the command, where the first argument is the controller name.
     */
    public function execute(array $args)
    {
        // Ensure a controller name is provided.
        if (empty($args)) {
            ConsoleOutput::print("&4[Error]&7 Please specify a controller name.");
            return;
        }

        // Format the controller name (convert to PascalCase).
        $controllerName = $this->formatControllerName($args[0]);

        // Ensure the controller name ends with "Controller".
        if (!preg_match('/Controller$/', $controllerName)) {
            $controllerName .= 'Controller';
        }

        // Convert to kebab-case for potential view paths.
        $kebabCaseName = $this->toKebabCase($args[0]);

        // Determine the full path where the controller will be created.
        $controllerPath = $this->basePath . $controllerName . '.php';

        // Ensure the directory exists.
        $this->ensureDirectoryExists(dirname($controllerPath));

        // Check if the controller already exists.
        if ($this->controllerExists($controllerPath)) {
            ConsoleOutput::print("&4[Error]&7 Controller '{$controllerName}' already exists.");
            return;
        }

        // Create the controller file using the template.
        try {
            $this->createFileFromTemplate('Controller.php', $controllerPath, $controllerName, $kebabCaseName);
            ConsoleOutput::print("&2[Success]&7 Controller '{$controllerName}' created successfully at '{$controllerPath}'. ");
        } catch (\Exception $e) {
            ConsoleOutput::print("&4[Error]&7 Failed to create controller '{$controllerName}'. " . $e->getMessage());
        }
    }

    /**
     * Formats the controller name to PascalCase.
     * 
     * This method converts a controller name like 'admin_user', 'admin-user', or 'adminUser'
     * into 'AdminUser' to ensure proper naming conventions.
     *
     * @param string $name The original controller name.
     * @return string The formatted controller name in PascalCase.
     */
    protected function formatControllerName(string $name): string
    {
        // Convert kebab-case or snake_case to PascalCase.
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }

    /**
     * Converts a string to kebab-case (lowercase with dashes).
     * 
     * Example: "TestUser" becomes "test-user".
     * 
     * @param string $string The input string to convert.
     * @return string The converted kebab-case string.
     */
    protected function toKebabCase(string $string): string
    {
        // Convert PascalCase or camelCase to kebab-case.
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $string));
    }

    /**
     * Ensure the directory for the controller exists.
     * 
     * This method ensures that the directory for the controller file exists.
     * If it doesn't, it creates the necessary directories.
     *
     * @param string $directory The path to the directory.
     */
    protected function ensureDirectoryExists(string $directory)
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new \Exception("Failed to create directory: '{$directory}'");
            }
        }
    }

    /**
     * Check if the controller file already exists.
     * 
     * This method checks if a file for the controller already exists to avoid overwriting
     * any existing controller.
     *
     * @param string $controllerPath The path to the controller file.
     * @return bool True if the file exists, false otherwise.
     */
    protected function controllerExists(string $controllerPath): bool
    {
        return file_exists($controllerPath);
    }

    /**
     * Copy a template file to the controllers directory and replace placeholders.
     * 
     * This method reads a template file (e.g., Controller.php), replaces
     * any placeholders (e.g., {{controllerName}} and {{kebabControllerName}}) with the actual controller name, and writes
     * the modified content to the destination file.
     *
     * @param string $templateFile The name of the template file (e.g., 'Controller.php').
     * @param string $destinationFile The full path to the destination file (e.g., the new controller's file).
     * @param string $controllerName The name of the controller (used to replace placeholders in the template).
     * @param string $kebabCaseName The kebab-case version of the controller name for view paths.
     * @throws \Exception If the template file cannot be written.
     */
    protected function createFileFromTemplate(string $templateFile, string $destinationFile, string $controllerName, string $kebabCaseName)
    {
        // Define the full path to the template file.
        $templatePath = $this->templatesPath . '/' . $templateFile;

        // Check if the template file exists.
        if (!file_exists($templatePath)) {
            throw new \Exception("Template '{$templateFile}' not found.");
        }

        // Read the content of the template file.
        $content = file_get_contents($templatePath);

        // Replace placeholders with the controller name and kebab-case view name.
        $content = str_replace('{{controllerName}}', $controllerName, $content);
        $content = str_replace('{{kebabControllerName}}', $kebabCaseName, $content);

        // Write the modified content to the destination file.
        if (file_put_contents($destinationFile, $content) === false) {
            throw new \Exception("Failed to write controller file '{$destinationFile}'.");
        }
    }
}
