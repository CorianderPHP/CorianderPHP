<?php
declare(strict_types=1);

/*
 * SQLManager offers static CRUD helpers utilising a shared DatabaseHandler
 * provided via `setDatabaseHandler` to minimise boilerplate across the
 * application.
 */

namespace CorianderCore\Core\Database;

use \PDO;
use Exception;
use CorianderCore\Core\Database\DatabaseHandler;
use CorianderCore\Core\Database\DatabaseException;
use CorianderCore\Core\Logging\StaticLoggerTrait;

/**
 * Helper providing static methods for common SQL operations.
 *
 * This class centralises simple CRUD queries using PDO. It relies on
 * {@see DatabaseHandler} for connection management and exposes convenience
 * wrappers for selecting, inserting, updating and deleting records without
 * repeatedly creating new PDO statements across the codebase. Errors are
 * surfaced through an injected PSR-3 logger.
 */
class SQLManager
{
    use StaticLoggerTrait;

    /**
     * @var DatabaseHandler|null Shared database handler instance.
     */
    private static ?DatabaseHandler $db = null;

    /**
     * Inject a DatabaseHandler instance.
     *
     * @param DatabaseHandler $handler Database handler to use.
     *
     * @return void
     */
    public static function setDatabaseHandler(DatabaseHandler $handler): void
    {
        self::$db = $handler;
    }

    /**
     * Retrieve the active DatabaseHandler, creating one if necessary.
     *
     * @return DatabaseHandler Database handler instance.
     */
    private static function getDatabaseHandler(): DatabaseHandler
    {
        if (self::$db === null) {
            self::$db = new DatabaseHandler(self::getLogger());
        }
        return self::$db;
    }

    /**
     * Retrieves all data from a table in the database.
     *
     * @param array  $columns The columns to select in the SQL query.
     * @param string $table   The table from which to retrieve data.
     * @param array  $params  Named parameters to bind to the SQL query (optional).
     *
     * @return array The retrieved data from the table as an associative array.
     *
     * @throws DatabaseException If the query fails.
     */
    public static function findAll(array $columns, string $table, array $params = []): array
    {
        try {
            $pdo        = self::getDatabaseHandler()->getPDO();
            $columnList = implode(', ', array_map([self::class, 'quoteIdentifier'], $columns));
            $table      = self::quoteIdentifier($table);
            $sql        = sprintf('SELECT %s FROM %s', $columnList, $table);
            $stmt       = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $data !== false ? $data : [];
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] findAll exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute findAll query.', 0, $e);
        }
    }

    /**
     * Retrieves rows from a table based on a condition.
     *
     * @param array  $columns The columns to select in the SQL query.
     * @param string $table   The table from which to retrieve data.
     * @param string $where   The condition to use for selecting rows.
     * @param array  $params  Named parameters to bind to the SQL query (optional).
     *
     * @return array The retrieved row data as an associative array.
     *
     * @throws DatabaseException If the query fails.
     */
    public static function findBy(array $columns, string $table, string $where, array $params = []): array
    {
        try {
            $pdo        = self::getDatabaseHandler()->getPDO();
            $columnList = implode(', ', array_map([self::class, 'quoteIdentifier'], $columns));
            $table      = self::quoteIdentifier($table);
            $sql        = sprintf('SELECT %s FROM %s WHERE %s', $columnList, $table, $where);
            $stmt       = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $data !== false ? $data : [];
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] findBy exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute findBy query.', 0, $e);
        }
    }

    /**
     * Updates data in a table in the database based on a given condition.
     *
     * @param string $table   The name of the table to update.
     * @param array  $data    Associative array of column => value pairs to update.
     * @param string $where   The condition to use for selecting the rows to update.
     * @param array  $params  Additional named parameters to bind to the SQL query (optional).
     *
     * @return bool True if the update was successful.
     *
     * @throws DatabaseException If the query fails.
     */
    public static function update(string $table, array $data, string $where, array $params = []): bool
    {
        try {
            $pdo   = self::getDatabaseHandler()->getPDO();
            $table = self::quoteIdentifier($table);
            $set   = [];
            foreach ($data as $column => $value) {
                $placeholder     = ':' . $column;
                $set[]           = sprintf('%s = %s', self::quoteIdentifier((string) $column), $placeholder);
                $params[$column] = $value;
            }
            $sql  = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $set), $where);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return true;
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] update exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute update query.', 0, $e);
        }
    }

    /**
     * Inserts a new row into a table in the database.
     *
     * @param string $table The name of the table into which to insert the row.
     * @param array  $data  Associative array of column => value pairs to insert.
     *
     * @return bool True if the insertion was successful.
     *
     * @throws DatabaseException If the query fails.
     */
    public static function insertInto(string $table, array $data): bool
    {
        try {
            $pdo         = self::getDatabaseHandler()->getPDO();
            $table       = self::quoteIdentifier($table);
            $columns     = array_map([self::class, 'quoteIdentifier'], array_keys($data));
            $placeholders = array_map(fn($col) => ':' . $col, array_keys($data));
            $sql         = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', $placeholders));
            $stmt        = $pdo->prepare($sql);
            $stmt->execute($data);

            return true;
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] insertInto exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute insert query.', 0, $e);
        }
    }

    /**
     * Inserts a new row into a table and returns the last inserted ID.
     *
     * @param string $table The name of the table to insert into.
     * @param array  $data  Associative array of column => value pairs to insert.
     *
     * @return string The last inserted ID on success.
     *
     * @throws DatabaseException If the query fails.
     */
    public static function insertIntoAndGetId(string $table, array $data): string
    {
        try {
            $pdo         = self::getDatabaseHandler()->getPDO();
            $table       = self::quoteIdentifier($table);
            $columns     = array_map([self::class, 'quoteIdentifier'], array_keys($data));
            $placeholders = array_map(fn($col) => ':' . $col, array_keys($data));
            $sql         = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', $placeholders));
            $stmt        = $pdo->prepare($sql);
            $stmt->execute($data);

            return $pdo->lastInsertId();
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] insertIntoAndGetId exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute insert query.', 0, $e);
        }
    }

    /**
     * Deletes rows from a table in the database based on a given condition.
     *
     * @param string $table  The name of the table from which to delete rows.
     * @param string $where  The condition to use for selecting the rows to delete.
     * @param array  $params Named parameters to bind to the SQL query (optional).
     *
     * @return bool True if the deletion was successful.
     *
     * @throws DatabaseException If the query fails.
     */
    public static function deleteFrom(string $table, string $where = '', array $params = []): bool
    {
        try {
            $pdo   = self::getDatabaseHandler()->getPDO();
            $table = self::quoteIdentifier($table);
            $sql   = $where === ''
                ? sprintf('DELETE FROM %s', $table)
                : sprintf('DELETE FROM %s WHERE %s', $table, $where);
            $stmt  = $pdo->prepare($sql);
            $stmt->execute($params);

            return true;
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] deleteFrom exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute delete query.', 0, $e);
        }
    }

    /**
     * Executes a given SQL query and retrieves a row of data from the database table.
     *
     * @param string $sqlScript The SQL query to execute.
     * @param array  $params    Named parameters to bind to the SQL query (optional).
     *
     * @return array The retrieved row data as an associative array.
     *
     * @throws DatabaseException If the query fails.
     */
    public static function sqlScript(string $sqlScript, array $params = []): array
    {
        try {
            $pdo  = self::getDatabaseHandler()->getPDO();
            $stmt = $pdo->prepare($sqlScript);
            $stmt->execute($params);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data !== false ? $data : [];
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] sqlScript exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute SQL script.', 0, $e);
        }
    }

    /**
     * Quote an identifier such as a table or column name.
     *
     * @param string $identifier Identifier to quote.
     *
     * @return string Quoted identifier safe for interpolation in SQL.
     */
    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
