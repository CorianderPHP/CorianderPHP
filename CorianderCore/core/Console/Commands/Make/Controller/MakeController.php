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
     * This method creates either a web or API controller based on the provided arguments.
     * It supports the `--api` flag to determine if an API controller should be generated
     * instead of a standard web controller.
     *
     * Steps performed:
     * - Parses the controller name and `--api` flag.
     * - Normalizes the controller name (PascalCase + "Controller" suffix).
     * - Chooses the appropriate template (API or Web).
     * - Ensures the target directory exists.
     * - Creates the controller file from a template with placeholder replacement.
     *
     * @param array $args The arguments passed to the command, where the first argument is the controller name.
     */
    public function execute(array $args)
    {
        if (empty($args)) {
            ConsoleOutput::print("&4[Error]&7 Please specify a controller name.");
            return;
        }

        $controllerNameRaw = $args[0];
        $isApi = in_array('--api', $args, true);

        $controllerName = $this->formatControllerName($controllerNameRaw);
        if (!preg_match('/Controller$/', $controllerName)) {
            $controllerName .= 'Controller';
        }

        $kebabCaseName = $this->toKebabCase($controllerNameRaw);
        $templateFile = $isApi ? 'ApiController.php' : 'WebController.php';
        $controllerSubdir = $isApi ? 'ApiControllers' : 'Controllers';
        $controllerPath = dirname($this->basePath) . "/{$controllerSubdir}/{$controllerName}.php";

        $this->ensureDirectoryExists(dirname($controllerPath));

        if ($this->controllerExists($controllerPath)) {
            ConsoleOutput::print("&4[Error]&7 Controller '{$controllerName}' already exists at '{$controllerPath}'.");
            return;
        }

        try {
            $this->createFileFromTemplate($templateFile, $controllerPath, $controllerName, $kebabCaseName);
            ConsoleOutput::print("&2[Success]&7 Controller '{$controllerName}' created successfully at '{$controllerPath}'. ");
        } catch (\Exception $e) {
            ConsoleOutput::print("&4[Error]&7 Failed to create controller: " . $e->getMessage());
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
