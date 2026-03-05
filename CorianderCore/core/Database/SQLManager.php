<?php
declare(strict_types=1);

/*
 * SQLManager offers static CRUD helpers utilising a shared DatabaseHandler
 * provided via `setDatabaseHandler` to minimise boilerplate across the
 * application.
 */

namespace CorianderCore\Core\Database;

use PDO;
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
     * Retrieve a valid PDO connection or throw a domain exception.
     *
     * @return PDO
     *
     * @throws DatabaseException When no database connection is configured.
     */
    private static function requirePdo(): PDO
    {
        $pdo = self::getDatabaseHandler()->getPDO();
        if ($pdo === null) {
            throw new DatabaseException('No active database connection. Check database configuration.');
        }

        return $pdo;
    }

    /**
     * Retrieves data from a table in the database.
     *
     * Supported signatures:
     * - findAll('users')
     * - findAll(['id', 'email'], 'users')
     *
     * Not recommended wildcard form:
     * - findAll(['*'], 'users')
     *
     * @param array|string    $columnsOrTable Columns list or table name.
     * @param string|null     $table          Table name when first argument is a columns list.
     * @param array           $params         Named parameters to bind to the SQL query (optional).
     *
     * @return array The retrieved data from the table as an associative array.
     *
     * @throws DatabaseException If the query fails.
     */
    public static function findAll(array|string $columnsOrTable, ?string $table = null, array $params = []): array
    {
        try {
            [$columns, $resolvedTable, $resolvedParams] = self::resolveFindAllArguments($columnsOrTable, $table, $params);

            $pdo = self::requirePdo();
            $columnList = self::buildColumnList($columns);
            $resolvedTable = self::quoteIdentifier($resolvedTable);
            $sql = sprintf('SELECT %s FROM %s', $columnList, $resolvedTable);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($resolvedParams);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $data !== false ? $data : [];
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] findAll exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute findAll query.', 0, $e);
        }
    }
    /**
     * @param array|string $columnsOrTable
     * @param string|null $table
     * @return array{0:array<int,string>,1:string,2:array<string,mixed>}
     */
    private static function resolveFindAllArguments(array|string $columnsOrTable, ?string $table, array $params): array
    {
        if (is_string($columnsOrTable)) {
            return [['*'], $columnsOrTable, $params];
        }

        if ($table === null || $table === '') {
            throw new DatabaseException('Table name is required when providing explicit column list.');
        }

        return [$columnsOrTable, $table, $params];
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
     *
     * @deprecated Use findWhere() for simple equality conditions.
     */
    public static function findBy(array $columns, string $table, string $where, array $params = []): array
    {
        try {
            $pdo = self::requirePdo();
            $columnList = self::buildColumnList($columns);
            $table = self::quoteIdentifier($table);
            $sql = sprintf('SELECT %s FROM %s WHERE %s', $columnList, $table, $where);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $data !== false ? $data : [];
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] findBy exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute findBy query.', 0, $e);
        }
    }

    /**
     * Retrieves rows from a table using an equality-based condition map.
     *
     * @param array<string,mixed> $conditions Associative column => value conditions.
     */
    public static function findWhere(array $columns, string $table, array $conditions): array
    {
        [$whereClause, $params] = self::buildWhereFromArray($conditions, 'w_');
        return self::findBy($columns, $table, $whereClause, $params);
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
     *
     * @deprecated Use updateWhere() for simple equality conditions.
     */
    public static function update(string $table, array $data, string $where, array $params = []): bool
    {
        try {
            $pdo = self::requirePdo();
            $table = self::quoteIdentifier($table);
            $set = [];
            foreach ($data as $column => $value) {
                $placeholder = ':' . $column;
                $set[] = sprintf('%s = %s', self::quoteIdentifier((string) $column), $placeholder);
                $params[$column] = $value;
            }
            $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $set), $where);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return true;
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] update exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute update query.', 0, $e);
        }
    }

    /**
     * Updates rows in a table using an equality-based condition map.
     *
     * @param array<string,mixed> $data       Associative array of column => value pairs to update.
     * @param array<string,mixed> $conditions Associative column => value conditions.
     */
    public static function updateWhere(string $table, array $data, array $conditions): bool
    {
        [$whereClause, $whereParams] = self::buildWhereFromArray($conditions, 'w_');
        return self::update($table, $data, $whereClause, $whereParams);
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
            $pdo = self::requirePdo();
            $table = self::quoteIdentifier($table);
            $columns = array_map([self::class, 'quoteIdentifier'], array_keys($data));
            $placeholders = array_map(fn($col) => ':' . $col, array_keys($data));
            $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', $placeholders));
            $stmt = $pdo->prepare($sql);
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
            $pdo = self::requirePdo();
            $table = self::quoteIdentifier($table);
            $columns = array_map([self::class, 'quoteIdentifier'], array_keys($data));
            $placeholders = array_map(fn($col) => ':' . $col, array_keys($data));
            $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', $placeholders));
            $stmt = $pdo->prepare($sql);
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
     *
     * @deprecated Use deleteWhere() for simple equality conditions.
     */
    public static function deleteFrom(string $table, string $where = '', array $params = []): bool
    {
        try {
            $pdo = self::requirePdo();
            $table = self::quoteIdentifier($table);
            $sql = $where === ''
                ? sprintf('DELETE FROM %s', $table)
                : sprintf('DELETE FROM %s WHERE %s', $table, $where);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return true;
        } catch (Exception $e) {
            self::getLogger()->error('[SQLManager] deleteFrom exception', ['exception' => $e]);
            throw new DatabaseException('Unable to execute delete query.', 0, $e);
        }
    }

    /**
     * Deletes rows in a table using an equality-based condition map.
     *
     * @param array<string,mixed> $conditions Associative column => value conditions.
     */
    public static function deleteWhere(string $table, array $conditions): bool
    {
        [$whereClause, $params] = self::buildWhereFromArray($conditions, 'w_');
        return self::deleteFrom($table, $whereClause, $params);
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
            $pdo = self::requirePdo();
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
     * Build a safe equality-based WHERE clause from an associative array.
     *
     * @param array<string,mixed> $conditions
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function buildWhereFromArray(array $conditions, string $placeholderPrefix): array
    {
        if ($conditions === []) {
            throw new DatabaseException('Conditions array cannot be empty.');
        }

        $clauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $safeColumn = self::quoteIdentifier((string) $column);
            $placeholder = ':' . $placeholderPrefix . preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $column);
            $index = 1;
            $basePlaceholder = $placeholder;
            while (array_key_exists(ltrim($placeholder, ':'), $params)) {
                $placeholder = $basePlaceholder . '_' . $index;
                $index++;
            }

            $clauses[] = $safeColumn . ' = ' . $placeholder;
            $params[ltrim($placeholder, ':')] = $value;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Build a safe select list from requested columns.
     *
     * @param array<int,string> $columns
     */
    private static function buildColumnList(array $columns): string
    {
        return implode(', ', array_map(static function ($column): string {
            $column = (string) $column;
            if ($column === '*') {
                return '*';
            }

            if (str_ends_with($column, '.*')) {
                $prefix = substr($column, 0, -2);
                if ($prefix !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $prefix) === 1) {
                    return self::quoteIdentifier($prefix) . '.*';
                }
            }

            return self::quoteIdentifier($column);
        }, $columns));
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
