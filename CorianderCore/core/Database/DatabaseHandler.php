<?php
declare(strict_types=1);

/*
 * DatabaseHandler manages a single PDO connection shared across the
 * application, supporting MySQL and SQLite with optional auto-closing.
 */

namespace CorianderCore\Core\Database;

use \PDO;
use Psr\Log\LoggerInterface;
use CorianderCore\Core\Logging\Logger;

/**
 * DatabaseHandler is a Singleton class responsible for managing a single
 * connection to the database using the PDO extension.
 * This implementation supports both MySQL and SQLite databases and reports
 * connection issues through an injected PSR-3 logger.
 */
class DatabaseHandler
{
    /**
     * @var DatabaseHandler|null Singleton instance of the DatabaseHandler class.
     */
    private static ?DatabaseHandler $instance = null;

    /**
     * @var LoggerInterface Logger used for reporting connection issues.
     */
    private LoggerInterface $logger;

    /**
     * @var PDO|null The PDO instance used for database connection, or null if unsupported.
     */
    private ?PDO $pdo = null;

    /**
     * @var bool Auto-close flag to determine if the connection should close automatically.
     */
    private static bool $autoCloseConnection = true;

    /**
     * Private constructor to prevent direct instantiation.
     * Establishes a connection to the MySQL or SQLite database based on the 'DB_TYPE' constant.
     * If an unsupported database type is specified, it logs a warning and skips connection.
     *
     * @param LoggerInterface $logger Logger instance for reporting issues.
     */
    private function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

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
                    $this->logger->warning('Unsupported database type: ' . DB_TYPE);
                    return; // Do not create a connection
            }

            if ($this->pdo !== null) {
                $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        } else {
            // Handle case where DB_TYPE is not defined
            $this->logger->warning('DB_TYPE is not defined. No database connection established.');
        }
    }

    /**
     * Retrieves the Singleton instance of the DatabaseHandler.
     * If no instance exists, a new one will be created.
     * 
     * @param LoggerInterface|null $logger Optional logger to use for the instance.
     *
     * @return DatabaseHandler The Singleton instance of the DatabaseHandler.
     */
    public static function getInstance(?LoggerInterface $logger = null): DatabaseHandler
    {
        if (self::$instance === null) {
            $logger = $logger ?? new Logger();
            self::$instance = new DatabaseHandler($logger);
        }
        return self::$instance;
    }

    /**
     * Returns the PDO instance associated with the current database connection.
     * 
     * @return PDO|null The PDO instance for interacting with the database, or null if no connection is available.
     */
    public function getPDO(): ?PDO
    {
        return $this->pdo;
    }

    /**
     * Set whether the connection should automatically close after each query.
     *
     * @param bool $autoClose Whether to automatically close the connection after each query.
     * @return void
     */
    public static function setAutoCloseConnection(bool $autoClose): void
    {
        self::$autoCloseConnection = $autoClose;
    }

    /**
     * Closes the database connection and resets the Singleton instance.
     * If auto-close is disabled, it simply returns without closing the connection.
     *
     * @return void
     */
    public function close(): void
    {
        if (!self::$autoCloseConnection) {
            return;
        }
        $this->pdo = null;
        self::$instance = null;
    }
}
