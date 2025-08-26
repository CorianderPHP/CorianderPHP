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
     * @param string $columns The columns to select in the SQL query.
     * @param string $from The name of the table from which to retrieve data.
     * @param array  $params Named parameters to bind to the SQL query (optional).
     * @return array|false The retrieved data from the table as an associative array or false on failure.
     */
    public static function findAll(string $columns, string $from, array $params = []): array|false
    {
        try {
            $pdo = self::getDatabaseHandler()->getPDO();
            $sql = "SELECT $columns FROM $from";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data != false ? $data : false;
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] findAll exception', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Retrieves a row from a table in the database based on a condition.
     *
     * @param string $columns The columns to select in the SQL query.
     * @param string $from The name of the table from which to retrieve data.
     * @param string $where The condition to use for selecting the row.
     * @param array  $params Named parameters to bind to the SQL query (optional).
     * @return array|false The retrieved row data as an associative array or false on failure.
     */
    public static function findBy(string $columns, string $from, string $where, array $params = []): array|false
    {
        try {
            $pdo = self::getDatabaseHandler()->getPDO();
            $sql = "SELECT $columns FROM $from WHERE $where";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data != false ? $data : false;
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] findBy exception', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Updates data in a table in the database based on a given condition.
     *
     * @param string $table  The name of the table to update.
     * @param string $set    The columns and values to update in the table.
     * @param string $where  The condition to use for selecting the rows to update.
     * @param array  $params Named parameters to bind to the SQL query (optional).
     * @return bool True if the update was successful, false otherwise.
     */
    public static function update(string $table, string $set, string $where, array $params = []): bool
    {
        try {
            $pdo = self::getDatabaseHandler()->getPDO();
            $sql = "UPDATE $table SET $set WHERE $where";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] update exception', ['exception' => $e]);
            return false;
        }
        return true;
    }

    /**
     * Inserts a new row into a table in the database.
     * 
     * @param string $table  The name of the table into which to insert the row.
     * @param string $into   The columns into which to insert values.
     * @param string $values The values to insert.
     * @param array  $params Parameters to bind to the prepared statement (optional).
     *
     * @return bool True if the insertion was successful, false otherwise.
     */
    public static function insertInto(string $table, string $into, string $values, array $params = []): bool
    {
        try {
            $pdo = self::getDatabaseHandler()->getPDO();
            $sql = "INSERT INTO $table $into VALUES $values";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] insertInto exception', ['exception' => $e]);
            return false;
        }
        return true;
    }

    /**
     * Inserts a new row into a table and returns the last inserted ID.
     * 
     * @param string $table  The name of the table to insert into.
     * @param string $into   The columns to insert into.
     * @param string $values The values to insert.
     * @param array  $params The parameters to bind to the prepared statement.
     * @return string|false The last inserted ID on success, false on failure.
     */
    public static function insertIntoAndGetId(string $table, string $into, string $values, array $params = []): string|false
    {
        try {
            $pdo = self::getDatabaseHandler()->getPDO();
            $sql = "INSERT INTO $table $into VALUES $values";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $lastInsertId = $pdo->lastInsertId();
            return $lastInsertId;
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] insertIntoAndGetId exception', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Deletes rows from a table in the database based on a given condition.
     *
     * @param string $table  The name of the table from which to delete rows.
     * @param string $where  The condition to use for selecting the rows to delete.
     * @param array  $params Named parameters to bind to the SQL query (optional).
     * @return bool True if the deletion was successful, false otherwise.
     */
    public static function deleteFrom(string $table, string $where = '', array $params = []): bool
    {
        try {
            $pdo = self::getDatabaseHandler()->getPDO();
            if ($where === '' || empty($where)) {
                $sql = "DELETE FROM $table";
            } else {
                $sql = "DELETE FROM $table WHERE $where";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] deleteFrom exception', ['exception' => $e]);
            return false;
        }
        return true;
    }

    /**
     * Executes a given SQL query and retrieves a row of data from the database table.
     *
     * @param string $sqlScript The SQL query to execute.
     * @param array  $params    Named parameters to bind to the SQL query (optional).
     * @return array|false The retrieved row data as an associative array or false on failure.
     */
    public static function sqlScript(string $sqlScript, array $params = []): array|false
    {
        $pdo = self::getDatabaseHandler()->getPDO();
        $stmt = $pdo->prepare($sqlScript);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($data);
    }
}
