<?php
// Define the root directory and project URL
define('PROJECT_ROOT', dirname(__DIR__, 1));
define('PROJECT_URL', '');

// Check if the database.php file exists in the config folder
$databaseConfigFile = PROJECT_ROOT . '/config/database.php';

if (file_exists($databaseConfigFile)) {
    // Include the database configuration file
    include_once $databaseConfigFile;
}