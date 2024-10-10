<?php

namespace CorianderCore\Console\Commands\Make\Sitemap;

use CorianderCore\Console\ConsoleOutput;

/**
 * The MakeSitemap class is responsible for generating the sitemap.php file
 * in the appropriate directory if the developer chooses to create a sitemap.
 */
class MakeSitemap
{
    /**
     * @var string $templatesPath The path to the directory containing the sitemap template.
     */
    protected string $templatesPath;

    /**
     * Constructor for the MakeSitemap class.
     * Initializes the path to the directory where sitemap templates are stored.
     */
    public function __construct()
    {
        // Set the path to the templates directory.
        $this->templatesPath = PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/Sitemap/templates';
    }

    /**
     * Executes the sitemap creation process.
     * 
     * This method handles the creation of the sitemap by:
     * - Verifying if a sitemap already exists.
     * - Creating the necessary file from a template.
     *
     * @param array $args The arguments passed to the command, where the first argument is optional.
     */
    public function execute(array $args = []): void
    {
        try {
            // Define the path where the sitemap will be generated.
            $sitemapFilePath = PROJECT_ROOT . '/public/sitemap.php';

            // Guard clause to prevent overwriting an existing sitemap.
            if ($this->sitemapExists($sitemapFilePath)) {
                throw new \Exception("Error: Sitemap already exists at '{$sitemapFilePath}'.");
            }

            // Create the sitemap file from the template.
            $this->createFileFromTemplate('sitemap.php', $sitemapFilePath);

            // Success message after sitemap creation.
            ConsoleOutput::print("&2[Success]&r&7 Sitemap created successfully at '{$sitemapFilePath}'.");

        } catch (\Exception $e) {
            // Handle any exceptions during the creation process.
            ConsoleOutput::print("&4[Error]&7 " . $e->getMessage());
        }
    }

    /**
     * Checks if the sitemap file already exists.
     *
     * @param string $sitemapFilePath The path to the sitemap.php file.
     * @return bool True if the sitemap exists, false otherwise.
     */
    protected function sitemapExists(string $sitemapFilePath): bool
    {
        return file_exists($sitemapFilePath);
    }

    /**
     * Copy a template file to the sitemap directory and replace placeholders if needed.
     * 
     * This method reads a template file (e.g., sitemap.php), and writes
     * the content to the destination file.
     *
     * @param string $templateFile The name of the template file (e.g., 'sitemap.php').
     * @param string $destinationFile The full path to the destination file (e.g., the new sitemap.php).
     */
    protected function createFileFromTemplate(string $templateFile, string $destinationFile): void
    {
        try {
            // Define the full path to the template file.
            $templatePath = $this->templatesPath . '/' . $templateFile;

            // Guard clause: Check if the template file exists.
            if (!file_exists($templatePath)) {
                throw new \Exception("Error: Template '{$templateFile}' not found.");
            }

            // Read the content of the template file.
            $content = file_get_contents($templatePath);

            // Write the content to the destination file.
            if (file_put_contents($destinationFile, $content) === false) {
                throw new \Exception("Error: Failed to write to file '{$destinationFile}'.");
            }
        } catch (\Exception $e) {
            throw new \Exception("Error during file creation: " . $e->getMessage());
        }
    }
}
