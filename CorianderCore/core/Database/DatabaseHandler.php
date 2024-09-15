<?php

namespace CorianderCore\Database;

use \PDO;
use \Exception;

/**
 * DatabaseHandler is a Singleton class responsible for managing a single
 * connection to the database using the PDO extension.
 * This implementation supports both MySQL and SQLite databases.
 */
class DatabaseHandler
{
    /**
     * @var DatabaseHandler|null Singleton instance of the DatabaseHandler class.
     */
    private static $instance = null;

    /**
     * @var PDO|null The PDO instance used for database connection, or null if unsupported.
     */
    private $pdo = null;

    /**
     * Private constructor to prevent direct instantiation.
     * Establishes a connection to the MySQL or SQLite database based on the 'DB_TYPE' constant.
     * If an unsupported database type is specified, it logs a warning and skips connection.
     */
    private function __construct()
    {
        // Check if the DB_TYPE constant is set and its value
        if (defined('DB_TYPE')) {
            switch (DB_TYPE) {
                case 'mysql':
                    $this->pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
                    break;
                case 'sqlite':
                    $this->pdo = new PDO("sqlite:" . DB_NAME);
                    break;
                default:
                    // Handle unsupported DB_TYPE
                    error_log("Unsupported database type: " . DB_TYPE);
                    return; // Do not create a connection
            }

            if ($this->pdo !== null) {
                $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        } else {
            // Handle case where DB_TYPE is not defined
            error_log("DB_TYPE is not defined. No database connection established.");
        }
    }

    /**
     * Retrieves the Singleton instance of the DatabaseHandler.
     * If no instance exists, a new one will be created.
     * 
     * @return DatabaseHandler The Singleton instance of the DatabaseHandler.
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new DatabaseHandler();
        }
        return self::$instance;
    }

    /**
     * Returns the PDO instance associated with the current database connection.
     * 
     * @return PDO|null The PDO instance for interacting with the database, or null if no connection is available.
     */
    public function getPDO()
    {
        return $this->pdo;
    }

    /**
     * Closes the database connection and resets the Singleton instance.
     * This method sets the PDO instance to null and resets the Singleton instance
     * to allow for a new connection to be established in the future if necessary.
     */
    public function close()
    {
        $this->pdo = null;
        self::$instance = null;
    }
}
