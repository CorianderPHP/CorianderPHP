<?php
declare(strict_types=1);

/*
 * DatabaseHandler manages a single PDO connection shared across the
 * application, supporting MySQL and SQLite with optional auto-closing.
 *
 * Workflow:
 * 1. Instantiated via dependency injection (e.g. service container).
 * 2. getPDO() exposes the underlying connection for queries.
 * 3. close() releases the connection when no longer needed.
 */

namespace CorianderCore\Core\Database;

use PDO;
use PDOException;
use CorianderCore\Core\Logging\Logger;
use Psr\Log\LoggerInterface;

/**
 * DatabaseHandler manages a PDO connection using the provided logger.
 *
 * Instances are intended to be shared via a service container rather than
 * accessed through a singleton. The handler supports both MySQL and SQLite
 * connections and reports issues through the injected PSR-3 logger.
 */
class DatabaseHandler
{
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
     * Construct a new DatabaseHandler instance.
     * Establishes a connection to the MySQL or SQLite database based on the 'DB_TYPE' constant.
     * If an unsupported database type is specified, it logs a warning and skips connection.
     *
     * @param LoggerInterface|null $logger Logger instance for reporting issues; defaults to core Logger when null.
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new Logger();

        if (!defined('DB_TYPE')) {
            $this->logger->warning('DB_TYPE is not defined. No database connection established.');
            return;
        }

        try {
            switch (DB_TYPE) {
                case 'mysql':
                    $port = defined('DB_PORT') ? DB_PORT : null;
                    $charset = defined('DB_CHARSET') ? (string) DB_CHARSET : '';
                    $dsn = self::buildMysqlDsn((string) DB_HOST, (string) DB_NAME, $port, $charset);

                    $this->pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
                    break;
                case 'sqlite':
                    $this->pdo = new PDO('sqlite:' . DB_NAME);
                    break;
                default:
                    $this->logger->warning('Unsupported database type: ' . DB_TYPE);
                    return;
            }
        } catch (PDOException $exception) {
            $this->pdo = null;
            $this->logger->error('Database connection failed.', ['exception' => $exception]);
            return;
        }

        if ($this->pdo !== null) {
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    /**
     * Build a MySQL DSN string using framework defaults.
     */
    public static function buildMysqlDsn(string $host, string $database, int|string|null $port = null, ?string $charset = null): string
    {
        $dsn = 'mysql:host=' . $host;

        $normalizedPort = is_numeric($port) ? (int) $port : 0;
        if ($normalizedPort > 0) {
            $dsn .= ';port=' . $normalizedPort;
        }

        $normalizedCharset = is_string($charset) && $charset !== '' ? $charset : 'utf8mb4';

        return $dsn . ';dbname=' . $database . ';charset=' . $normalizedCharset;
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
     * Closes the database connection.
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
    }
}
