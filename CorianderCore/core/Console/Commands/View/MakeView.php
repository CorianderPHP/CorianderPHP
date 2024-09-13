<?php

namespace CorianderCore\Console\Commands\View;

/**
 * The MakeView class is responsible for generating new view directories and files
 * based on predefined templates. It facilitates the creation of views in the framework,
 * ensuring that the necessary files (e.g., `index.php` and `metadata.php`) are generated
 * in the correct location.
 */
class MakeView
{
    /**
     * @var string $templatesPath The path to the directory containing view templates.
     */
    protected $templatesPath;

    /**
     * Constructor for the MakeView class.
     * 
     * Initializes the path to the directory where view templates are stored.
     * The templates will be copied to the new view directories during view creation.
     */
    public function __construct()
    {
        // Set the path to the templates directory.
        $this->templatesPath = PROJECT_ROOT . '/CorianderCore/core/Console/Commands/View/templates';
    }

    /**
     * Executes the view creation process.
     * 
     * This method handles the creation of a new view by:
     * - Verifying if a view name is provided.
     * - Checking if the view already exists.
     * - Creating the necessary directory and copying template files.
     *
     * @param array $args The arguments passed to the command, where the first argument is the view name.
     */
    public function execute(array $args)
    {
        // Ensure a view name is provided.
        if (empty($args)) {
            echo "Error: Please specify a view name." . PHP_EOL;
            return;
        }

        // Get the view name and determine the path where the view will be created.
        $viewName = $args[0];
        $viewPath = PROJECT_ROOT . '/public/public_views/' . $viewName;

        // Check if the view already exists.
        if ($this->viewExists($viewPath)) {
            echo "Error: View '{$viewName}' already exists." . PHP_EOL;
            return;
        }

        // Create the view directory.
        $this->createDirectory($viewPath);

        // Copy the necessary template files (index.php and metadata.php) to the new view directory.
        $this->createFileFromTemplate('view.php', $viewPath . '/index.php', $viewName);
        $this->createFileFromTemplate('metadata.php', $viewPath . '/metadata.php', $viewName);

        echo "View '{$viewName}' created successfully at '{$viewPath}'." . PHP_EOL;
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
     * Create the directory for the new view.
     * 
     * This method creates a new directory for the view with the appropriate permissions.
     *
     * @param string $viewPath The path to the view directory.
     */
    protected function createDirectory(string $viewPath)
    {
        mkdir($viewPath, 0755, true); // Creates the directory with 0755 permissions.
    }

    /**
     * Copy a template file to the view directory and replace placeholders.
     * 
     * This method reads a template file (e.g., view.php or metadata.php), replaces
     * any placeholders (e.g., {{viewName}}) with the actual view name, and writes
     * the modified content to the destination file.
     *
     * @param string $templateFile The name of the template file (e.g., 'view.php').
     * @param string $destinationFile The full path to the destination file (e.g., the new view's index.php).
     * @param string $viewName The name of the view (used to replace placeholders in the template).
     */
    protected function createFileFromTemplate(string $templateFile, string $destinationFile, string $viewName)
    {
        // Define the full path to the template file.
        $templatePath = $this->templatesPath . '/' . $templateFile;

        // Check if the template file exists.
        if (!file_exists($templatePath)) {
            echo "Error: Template '{$templateFile}' not found." . PHP_EOL;
            return;
        }

        // Read the content of the template file.
        $content = file_get_contents($templatePath);

        // Replace any placeholders (e.g., {{viewName}}) with the actual view name.
        $content = str_replace('{{viewName}}', $viewName, $content);

        // Write the modified content to the destination file.
        file_put_contents($destinationFile, $content);
    }
}
