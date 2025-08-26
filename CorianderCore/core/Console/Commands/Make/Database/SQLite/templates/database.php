<?php
declare(strict_types=1);

// SQLite Configuration
define('DB_TYPE', 'sqlite');

// Path to the primary SQLite database file
$SQLitePath = PROJECT_ROOT . "/database/{{DB_NAME}}.sqlite";

define('DB_NAME', $SQLitePath);

// If the primary database file doesn't exist, create it from the clean copy
if (!file_exists($SQLitePath)) {
    $CleanSQLitePath = PROJECT_ROOT . "/database/clean_{{DB_NAME}}.sqlite";
    
    // Copy the clean database template if available
    if (file_exists($CleanSQLitePath)) {
        if (!copy($CleanSQLitePath, $SQLitePath)) {
            throw new Exception("Failed to create the database from clean copy.");
        }
    } else {
        throw new Exception("Clean SQLite database not found.");
    }
}
