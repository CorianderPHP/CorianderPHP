<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Make\Database\MySQL;

use CorianderCore\Core\Console\ConsoleOutput;
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
    protected string $templatesPath;

    /**
     * @var string Path to the config folder where the configuration file will be generated.
     */
    protected string $configPath;

    /**
     * Constructor to initialize paths for templates and configuration files.
     * 
     * @param string $configPath Path to the configuration folder (default: PROJECT_ROOT . '/config').
     */
    public function __construct(string $configPath = PROJECT_ROOT . '/config')
    {
        // Define paths to the MySQL templates and config folder
        $this->templatesPath = PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/Database/MySQL/templates';
        $this->configPath = $configPath;
    }

    /**
     * Executes the process of creating a MySQL configuration.
     * Prompts the user for MySQL connection details and generates a config file.
     */
    public function execute(): void
    {
        // Ask the user for MySQL connection details
        ConsoleOutput::print("Enter MySQL host:\n");
        $dbHost = trim((string)fgets(STDIN));

        ConsoleOutput::hr();
        ConsoleOutput::print("Enter MySQL database name:\n");
        $dbName = trim((string)fgets(STDIN));

        ConsoleOutput::hr();
        ConsoleOutput::print("Enter MySQL user:\n");
        $dbUser = trim((string)fgets(STDIN));

        ConsoleOutput::hr();
        ConsoleOutput::print("Enter MySQL password:\n");
        $dbPassword = trim((string)fgets(STDIN));

        // Test the MySQL connection
        try {
            $dsn = "mysql:host=$dbHost;dbname=$dbName";
            $pdo = new PDO($dsn, $dbUser, $dbPassword);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            ConsoleOutput::hr();
            ConsoleOutput::print("&2[Success]&r&7 Connection successful! Proceeding with configuration...");
        } catch (PDOException $e) {
            ConsoleOutput::hr();
            ConsoleOutput::print("&4[Error]&7 Connection failed: " . $e->getMessage());
            ConsoleOutput::print("Do you want to proceed anyway? (y/n):\n");
            $confirmation = strtolower(trim((string)fgets(STDIN)));
            if ($confirmation !== 'y') {
                ConsoleOutput::print("&e[Warning]&7 Database configuration file not created.");
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
    protected function generateConfig(string $dbHost, string $dbName, string $dbUser, string $dbPassword): void
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
        ConsoleOutput::print("&2[Success]&r&7 MySQL configuration generated successfully.");
    }

    /**
     * Saves the generated configuration to the specified config folder.
     *
     * @param string $content The content of the configuration file.
     */
    protected function saveConfig(string $content): void
    {
        // Ensure the config directory exists
        if (!is_dir($this->configPath)) {
            mkdir($this->configPath, 0777, true);
        }

        // Save the configuration content to a file
        file_put_contents($this->configPath . '/database.php', $content);
    }
}
