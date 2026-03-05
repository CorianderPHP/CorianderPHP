<?php
// Define the root directory and project URL
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 1));
}
if (!defined('PROJECT_URL')) {
    define('PROJECT_URL', '');
}
if (!defined('CORIANDER_UPDATE_BACKUP_DIR')) {
    define('CORIANDER_UPDATE_BACKUP_DIR', 'backups/coriander');
}
// Logging configuration via environment variables
if (!defined('LOG_CHANNEL')) {
    define('LOG_CHANNEL', getenv('LOG_CHANNEL') ?: 'stderr');
}
if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'warning');
}

// Check if the database.php file exists in the config folder
$databaseConfigFile = PROJECT_ROOT . '/config/database.php';

if (file_exists($databaseConfigFile)) {
    // Include the database configuration file
    include_once $databaseConfigFile;
}

