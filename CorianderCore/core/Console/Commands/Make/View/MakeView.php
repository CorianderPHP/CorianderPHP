<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Make\View;

use CorianderCore\Core\Console\ConsoleOutput;
use CorianderCore\Core\Utils\DirectoryHandler;

/**
 * The MakeView class is responsible for generating new view directories and files
 * based on predefined templates. It facilitates the creation of views in the framework,
 * ensuring that the necessary files (e.g., `index.php` and `metadata.php`) are generated
 * in the correct location.
 */
class MakeView
{
    /**
     * @var string The path to the directory containing view templates.
     */
    protected string $templatesPath;

    /**
     * @var string The path where the view will be created.
     */
    protected string $viewPath;

    /**
     * Constructor for the MakeView class.
     * 
     * Initializes the path to the directory where view templates are stored and
     * sets the path where new views will be created.
     * 
     * @param string $viewPath The path where the view will be created (default: PROJECT_ROOT . '/public/public_views/').
     */
    public function __construct(string $viewPath = PROJECT_ROOT . '/public/public_views/')
    {
        // Set the view creation path and templates path.
        $this->viewPath = $viewPath;
        $this->templatesPath = PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/View/templates';
    }

    /**
     * Executes the view creation process.
     * 
     * This method handles the creation of a new view by:
     * - Verifying if a view name is provided.
     * - Converting the view name to kebab-case.
     * - Checking if the view already exists.
     * - Creating the necessary directory and copying template files.
     *
     * @param array $args The arguments passed to the command, where the first argument is the view name.
     */
    public function execute(array $args): void
    {
        try {
            // Ensure a view name is provided.
            if (empty($args)) {
                throw new \Exception("Error: Please specify a view name.");
            }

            // Convert the view name to kebab-case (lowercase with dashes between words).
            $viewName = $this->toKebabCase($args[0]);
            $fullViewPath = $this->viewPath . $viewName;

            // Check if the view already exists.
            if ($this->viewExists($fullViewPath)) {
                throw new \Exception("Error: View '{$viewName}' already exists.");
            }

            // Create the view directory.
            DirectoryHandler::createDirectory($fullViewPath);

            // Copy the necessary template files (index.php and metadata.php) to the new view directory.
            $this->createFileFromTemplate('view.php', $fullViewPath . '/index.php', $viewName);
            $this->createFileFromTemplate('metadata.php', $fullViewPath . '/metadata.php', $viewName);

            // Output success message.
            ConsoleOutput::print("&2[Success]&r&7 View '{$viewName}' created successfully at '{$fullViewPath}'.");

        } catch (\Exception $e) {
            // Handle any exceptions during the creation process.
            ConsoleOutput::print("&4[Error]&7 " . $e->getMessage());
        }
    }

    /**
     * Converts a string to kebab-case (lowercase letters with dashes separating words).
     * 
     * Example: "TestController" becomes "test-controller".
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
     * Check if the view directory already exists.
     * 
     * This method checks if a directory for the view already exists to avoid overwriting
     * any existing view.
     *
     * @param string $viewPath The path to the view directory.
     * @return bool True if the directory exists, false otherwise.
     */
    protected function viewExists(string $viewPath): bool
    {
        return file_exists($viewPath);
    }

    /**
     * Copy a template file to the view directory and replace placeholders.
     * 
     * This method reads a template file (e.g., view.php or metadata.php), replaces
     * any placeholders (e.g., {{viewName}}) with the actual view name, and writes
     * the modified content to the destination file. Wrapped in try-catch for file handling errors.
     *
     * @param string $templateFile The name of the template file (e.g., 'view.php').
     * @param string $destinationFile The full path to the destination file (e.g., the new view's index.php).
     * @param string $viewName The name of the view (used to replace placeholders in the template).
     */
    protected function createFileFromTemplate(string $templateFile, string $destinationFile, string $viewName): void
    {
        try {
            // Define the full path to the template file.
            $templatePath = $this->templatesPath . '/' . $templateFile;

            // Check if the template file exists.
            if (!file_exists($templatePath)) {
                throw new \Exception("Error: Template '{$templateFile}' not found.");
            }

            // Read the content of the template file.
            $content = file_get_contents($templatePath);

            // Replace any placeholders (e.g., {{viewName}}) with the actual view name.
            $content = str_replace('{{viewName}}', $viewName, $content);

            // Write the modified content to the destination file.
            if (file_put_contents($destinationFile, $content) === false) {
                throw new \Exception("Error: Failed to write to file '{$destinationFile}'.");
            }
        } catch (\Exception $e) {
            throw new \Exception("Error during file creation: " . $e->getMessage());
        }
    }
}
