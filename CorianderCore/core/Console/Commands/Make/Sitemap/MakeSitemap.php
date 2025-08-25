<?php

namespace CorianderCore\Core\Console\Commands\Make\Sitemap;

use CorianderCore\Core\Console\ConsoleOutput;

/**
 * The MakeSitemap class is responsible for generating the sitemap.php file
 * in the appropriate directory if the developer chooses to create a sitemap.
 * 
 * This class handles the creation of a new sitemap by verifying whether a sitemap
 * already exists and, if not, creating the necessary file from a template.
 */
class MakeSitemap
{
    /**
     * @var string $templatesPath The path to the directory containing the sitemap template file.
     */
    protected string $templatesPath;

    /**
     * @var string $sitemapFilePath The path where the sitemap will be generated.
     */
    protected string $sitemapFilePath;

    /**
     * Constructor for the MakeSitemap class.
     * Initializes the path to the directory where sitemap templates are stored and the path where the sitemap will be created.
     * 
     * @param string $sitemapFilePath The path where the sitemap will be generated (default: PROJECT_ROOT . '/public/sitemap.php').
     */
    public function __construct(string $sitemapFilePath = PROJECT_ROOT . '/public/sitemap.php')
    {
        $this->sitemapFilePath = $sitemapFilePath;
        $this->templatesPath = PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/Sitemap/templates';
    }

    /**
     * Executes the sitemap creation process.
     * 
     * This method checks if a sitemap already exists at the specified path. If it does not exist, 
     * it creates the sitemap from a predefined template and stores it in the provided path.
     * 
     * - If a sitemap already exists, an error message is displayed.
     * - If the sitemap is successfully created, a success message is displayed.
     */
    public function execute(): void
    {
        try {
            // Guard clause to prevent overwriting an existing sitemap.
            if ($this->sitemapExists($this->sitemapFilePath)) {
                throw new \Exception("Error: Sitemap already exists at '{$this->sitemapFilePath}'.");
            }

            // Create the sitemap file from the template.
            $this->createFileFromTemplate('sitemap.php', $this->sitemapFilePath);

            // Success message after sitemap creation.
            ConsoleOutput::print("&2[Success]&r&7 Sitemap created successfully at '{$this->sitemapFilePath}'.");
        } catch (\Exception $e) {
            // Handle any exceptions during the creation process.
            ConsoleOutput::print("&4[Error]&7 " . $e->getMessage());
        }
    }

    /**
     * Checks if the sitemap file already exists.
     *
     * This method checks if a sitemap file already exists at the provided file path.
     *
     * @param string $sitemapFilePath The path to the sitemap.php file.
     * @return bool True if the sitemap exists, false otherwise.
     */
    protected function sitemapExists(string $sitemapFilePath): bool
    {
        return file_exists($sitemapFilePath);
    }

    /**
     * Copies a template file to the sitemap directory and writes its content to the destination.
     * 
     * This method reads the content of a template file (e.g., sitemap.php) and writes the content to
     * the destination file path. It throws exceptions in case of any file errors during the process.
     *
     * @param string $templateFile The name of the template file (e.g., 'sitemap.php').
     * @param string $destinationFile The full path to the destination file where the content will be written.
     * @throws \Exception If the template file does not exist or the destination file cannot be written.
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
