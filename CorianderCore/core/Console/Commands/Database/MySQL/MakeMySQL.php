<?php

namespace CorianderCore\Console\Commands\Database\MySQL;

use \PDO;
use \PDOException;

/**
 * The MakeMySQL class is responsible for generating and validating
 * the MySQL database configuration based on user input.
 */
class MakeMySQL
{
    /**
     * @var string Path to the MySQL templates.
     */
    protected $templatesPath;

    /**
     * @var string Path to the config folder where the configuration file will be generated.
     */
    protected $configPath;

    /**
     * Constructor to initialize paths for templates and configuration files.
     */
    public function __construct()
    {
        // Define paths to the MySQL templates and config folder
        $this->templatesPath = PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Database/MySQL/templates';
        $this->configPath = PROJECT_ROOT . '/config';
    }

    /**
     * Executes the process of creating a MySQL configuration.
     * Prompts the user for MySQL connection details and generates a config file.
     */
    public function execute()
    {
        // Ask the user for MySQL connection details
        echo "Enter MySQL host: ";
        $dbHost = trim(fgets(STDIN));

        echo "Enter MySQL database name: ";
        $dbName = trim(fgets(STDIN));

        echo "Enter MySQL user: ";
        $dbUser = trim(fgets(STDIN));

        echo "Enter MySQL password: ";
        $dbPassword = trim(fgets(STDIN));

        // Test the MySQL connection
        try {
            $dsn = "mysql:host=$dbHost;dbname=$dbName";
            $pdo = new PDO($dsn, $dbUser, $dbPassword);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connection successful! Proceeding with configuration...\n";
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage() . "\n";
            echo "Do you want to proceed anyway? (y/n): ";
            $confirmation = strtolower(trim(fgets(STDIN)));
            if ($confirmation !== 'y') {
                return;
            }
        }

        // Generate MySQL configuration
        $this->generateConfig($dbHost, $dbName, $dbUser, $dbPassword);
    }

    /**
     * Generates the MySQL configuration file based on the provided user input.
     *
     * @param string $dbHost The MySQL host.
     * @param string $dbName The MySQL database name.
     * @param string $dbUser The MySQL user.
     * @param string $dbPassword The MySQL password.
     */
    protected function generateConfig($dbHost, $dbName, $dbUser, $dbPassword)
    {
        // Load the MySQL template file
        $templatePath = $this->templatesPath . '/database.php';
        $content = file_get_contents($templatePath);

        // Replace placeholders in the template with actual values
        $content = str_replace('{{DB_HOST}}', $dbHost, $content);
        $content = str_replace('{{DB_NAME}}', $dbName, $content);
        $content = str_replace('{{DB_USER}}', $dbUser, $content);
        $content = str_replace('{{DB_PASSWORD}}', $dbPassword, $content);

        // Save the generated configuration file
        $this->saveConfig($content);
        echo "MySQL configuration generated successfully.\n";
    }

    /**
     * Saves the generated configuration to the specified config folder.
     *
     * @param string $content The content of the configuration file.
     */
    protected function saveConfig(string $content)
    {
        // Ensure the config directory exists
        if (!is_dir($this->configPath)) {
            mkdir($this->configPath, 0777, true);
        }

        // Save the configuration content to a file
        file_put_contents($this->configPath . '/database.php', $content);
    }
}
