<?php

namespace CorianderCore\Database;

use \PDO;
use Exception;
use CorianderCore\Database\DatabaseHandler;

class SQLManager
{
    /**
     * Retrieves all data from a table in the database.
     *
     * @param string $columns The columns to select in the SQL query.
     * @param string $from The name of the table from which to retrieve data.
     * @param array $params Named parameters to bind to the SQL query (optional).
     * @return array The retrieved data from the table as an associative array.
     */
    public static function findAll($columns, $from, $params = array())
    {
        try {
            $db = DatabaseHandler::getInstance();
            $pdo = $db->getPDO();
            $sql = "SELECT $columns FROM $from";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $db->close();
            return $data != false ? $data : false;
        } catch (Exception $e) {
            error_log("[SQLManager.php] - SQLManager::findAll Exception : $e", 0);
            return false;
        }
    }

    /**
     * Retrieves a row from a table in the database based on a condition.
     *
     * @param string $columns The columns to select in the SQL query.
     * @param string $from The name of the table from which to retrieve data.
     * @param string $where The condition to use for selecting the row.
     * @param array $params Named parameters to bind to the SQL query (optional).
     * @return array The retrieved row data as an associative array.
     */
    public static function findBy($columns, $from, $where, $params = array())
    {
        try {
            $db = DatabaseHandler::getInstance();
            $pdo = $db->getPDO();
            $sql = "SELECT $columns FROM $from WHERE $where";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $db->close();
            return $data != false ? $data : false;
        } catch (Exception $e) {
            error_log("[SQLManager.php] - SQLManager::findBy Exception : $e", 0);
            return false;
        }
    }

    /**
     * Updates data in a table in the database based on a given condition.
     *
     * @param string $table The name of the table to update.
     * @param string $set The columns and values to update in the table.
     * @param string $where The condition to use for selecting the rows to update.
     * @param array $params Named parameters to bind to the SQL query (optional).
     * @return bool True if the update was successful, false otherwise.
     */
    public static function update($table, $set, $where, $params = array())
    {
        try {
            $db = DatabaseHandler::getInstance();
            $pdo = $db->getPDO();
            $sql = "UPDATE $table SET $set WHERE $where";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $db->close();
        } catch (Exception $e) {
            error_log("[SQLManager.php] - SQLManager::update Exception : $e", 0);
            return false;
        }
        return true;
    }

    /**
     * Inserts a new row into a table in the database.
     * 
     * @param string $table The name of the table into which to insert the row.
     * @param string $into The columns into which to insert values.
     * @param string $values The values to insert.
     * @param array $params Parameters to bind to the prepared statement (optional).
     * 
     * @return bool True if the insertion was successful, false otherwise.
     */
    public static function insertInto($table, $into, $values, $params = array())
    {
        try {
            $db = DatabaseHandler::getInstance();
            $pdo = $db->getPDO();
            $sql = "INSERT INTO $table $into VALUES $values";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $db->close();
        } catch (Exception $e) {
            error_log("[SQLManager.php] - SQLManager::insertInto Exception : $e", 0);
            return false;
        }
        return true;
    }

    /**
     * Inserts a new row into a table and returns the last inserted ID.
     * 
     * @param string $table The name of the table to insert into.
     * @param string $into The columns to insert into.
     * @param string $values The values to insert.
     * @param array $params The parameters to bind to the prepared statement.
     * @return mixed The last inserted ID on success, false on failure.
     */
    public static function insertIntoAndGetId($table, $into, $values, $params = array())
    {
        try {
            $db = DatabaseHandler::getInstance();
            $pdo = $db->getPDO();
            $sql = "INSERT INTO $table $into VALUES $values";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $lastInsertId = $pdo->lastInsertId();
            $db->close();
            return $lastInsertId;
        } catch (Exception $e) {
            error_log("[SQLManager.php] - SQLManager::insertIntoAndGetId Exception : $e", 0);
            return false;
        }
    }

    /**
     * Deletes rows from a table in the database based on a given condition.
     *
     * @param string $table The name of the table from which to delete rows.
     * @param string $where The condition to use for selecting the rows to delete.
     * @param array $params Named parameters to bind to the SQL query (optional).
     * @return bool True if the deletion was successful, false otherwise.
     */
    public static function deleteFrom($table, $where = '', $params = array())
    {
        try {
            $db = DatabaseHandler::getInstance();
            $pdo = $db->getPDO();
            if ($where === '' || empty($where)) {
                $sql = "DELETE FROM $table";
            } else {
                $sql = "DELETE FROM $table WHERE $where";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $db->close();
        } catch (Exception $e) {
            error_log("[SQLManager.php] - SQLManager::deleteFrom Exception : $e", 0);
            return false;
        }
        return true;
    }

    /**
     * Executes a given SQL query and retrieves a row of data from the database table.
     *
     * @param string $sqlScript The SQL query to execute.
     * @param array $params Named parameters to bind to the SQL query (optional).
     * @return array The retrieved row data as an associative array.
     */
    public static function sqlScript($sqlScript, $params = array())
    {
        $db = DatabaseHandler::getInstance();
        $pdo = $db->getPDO();
        $stmt = $pdo->prepare($sqlScript);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $db->close();
        return ($data);
    }
}