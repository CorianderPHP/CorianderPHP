<?php

namespace CorianderCore\Console\Commands\Make\Database\SQLite;

use CorianderCore\Console\ConsoleOutput;

/**
 * The MakeSQLite class is responsible for generating and setting up SQLite database configurations.
 */
class MakeSQLite
{
    /**
     * @var string Path to the SQLite templates.
     */
    protected $templatesPath;

    /**
     * @var string Path to the configuration folder where the SQLite configuration will be saved.
     */
    protected $configPath;

    /**
     * @var string Path to the database folder where SQLite database files are stored.
     */
    protected $databaseFolder;

    /**
     * Constructor to initialize paths for templates and configuration files.
     */
    public function __construct()
    {
        // Define paths to the SQLite templates and configuration folder
        $this->templatesPath = PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/Database/SQLite/templates';
        $this->configPath = PROJECT_ROOT . '/config';
        $this->databaseFolder = PROJECT_ROOT . '/database';
    }

    /**
     * Executes the process of creating an SQLite configuration.
     * Prompts the user for the SQLite database name and generates necessary files.
     */
    public function execute()
    {
        // Ask the user for the SQLite database name
        ConsoleOutput::print("Enter SQLite database name (without extension):\n");
        $dbName = trim(fgets(STDIN));
        ConsoleOutput::hr();

        // Generate SQLite configuration and database files
        $this->generateConfig($dbName);
        $this->createDatabaseFiles($dbName);
        ConsoleOutput::hr();
        ConsoleOutput::print("&2[Success]&r&7 Database " . $dbName . ".sqlite created in folder: " . $this->databaseFolder);
    }

    /**
     * Generates the SQLite configuration file based on user input.
     *
     * @param string $dbName The name of the SQLite database.
     */
    protected function generateConfig($dbName)
    {
        // Load the SQLite configuration template
        $templatePath = $this->templatesPath . '/database.php';
        $content = file_get_contents($templatePath);

        // Replace placeholders with actual values
        $content = str_replace('{{DB_NAME}}', $dbName, $content);

        // Save the generated configuration file
        $this->saveConfig($content);
    }

    /**
     * Creates the necessary SQLite database files, including a clean and a data version.
     *
     * @param string $dbName The name of the SQLite database.
     */
    protected function createDatabaseFiles($dbName)
    {
        // Ensure the database folder exists
        if (!is_dir($this->databaseFolder)) {
            mkdir($this->databaseFolder, 0777, true);
            ConsoleOutput::print("&8[Info] Database folder created at $this->databaseFolder.");
        }

        // Paths for clean and data SQLite files
        $cleanFilePath = $this->databaseFolder . '/clean_' . $dbName . '.sqlite';
        $dataFilePath = $this->databaseFolder . '/' . $dbName . '.sqlite';

        // Create clean SQLite file if it doesn't exist
        if (!file_exists($cleanFilePath)) {
            copy($this->templatesPath . '/database.sqlite', $cleanFilePath);
            ConsoleOutput::print("&8[Info] Clean SQLite database file created at $cleanFilePath.");
        }

        // Create data SQLite file if it doesn't exist
        if (!file_exists($dataFilePath)) {
            copy($this->templatesPath . '/database.sqlite', $dataFilePath);
            ConsoleOutput::print("&8[Info] SQLite database file created at $dataFilePath.");
        }

        // Protect the database with an .htaccess file
        copy($this->templatesPath . '/.htaccess', $this->databaseFolder . '/.htaccess');
        ConsoleOutput::print("&8[Info] .htaccess file created to protect the SQLite database.");

        // Create a .gitignore file
        $this->createGitignore($dbName);
    }

    /**
     * Generates a .gitignore file to prevent SQLite databases from being added to version control.
     *
     * @param string $dbName The name of the SQLite database.
     */
    protected function createGitignore($dbName)
    {
        $gitignoreTemplate = file_get_contents($this->templatesPath . '/.gitignore');
        $gitignoreContent = str_replace('{{DB_NAME}}', $dbName, $gitignoreTemplate);

        // Save the .gitignore file in the database folder
        file_put_contents($this->databaseFolder . '/.gitignore', $gitignoreContent);
        ConsoleOutput::print("&8[Info] .gitignore file created in the database folder.");
    }

    /**
     * Saves the generated configuration to the specified configuration folder.
     *
     * @param string $content The content of the configuration file.
     */
    protected function saveConfig(string $content)
    {
        // Ensure the configuration folder exists
        if (!is_dir($this->configPath)) {
            mkdir($this->configPath, 0777, true);
        }

        // Save the configuration file
        file_put_contents($this->configPath . '/database.php', $content);
    }
}
