<?php
declare(strict_types=1);

namespace CorianderCore\Core\Database;

use PDO;
use PDOException;
use CorianderCore\Core\Logging\Logger;
use Psr\Log\LoggerInterface;

/**
 * DatabaseHandler manages a PDO connection using the provided logger.
 *
 * Each instance owns its MySQL or SQLite connection and reports issues through
 * the injected PSR-3 logger. Pass shared instances to application services.
 */
class DatabaseHandler
{
    private LoggerInterface $logger;

    private ?PDO $pdo = null;

    private static bool $defaultAutoCloseConnection = true;

    private bool $autoCloseConnection;

    /**
     * Connect eagerly using DB_TYPE configuration; log failures and leave PDO null.
     */
    public function __construct(?LoggerInterface $logger = null, ?bool $autoCloseConnection = null)
    {
        $this->logger = $logger ?? new Logger();
        $this->autoCloseConnection = $autoCloseConnection ?? self::$defaultAutoCloseConnection;

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

    /** Return null when configuration is absent/unsupported or connection failed. */
    public function getPDO(): ?PDO
    {
        return $this->pdo;
    }

    /** Change the close() policy for subsequently constructed handlers only. */
    public static function setAutoCloseConnection(bool $autoClose): void
    {
        self::$defaultAutoCloseConnection = $autoClose;
    }

    /** Change this instance's close() policy. */
    public function setAutoClose(bool $autoClose): void
    {
        $this->autoCloseConnection = $autoClose;
    }

    /** Release this handler's PDO reference if its auto-close policy allows it. */
    public function close(): void
    {
        if (!$this->autoCloseConnection) {
            return;
        }
        $this->pdo = null;
    }
}
