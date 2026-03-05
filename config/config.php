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
if (!defined('TRUSTED_PROXIES')) {
    define('TRUSTED_PROXIES', getenv('TRUSTED_PROXIES') ?: '127.0.0.1,::1');
}


// Logging configuration via environment variables
if (!defined('LOG_CHANNEL')) {
    define('LOG_CHANNEL', getenv('LOG_CHANNEL') ?: 'stderr');
}
if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'warning');
}
if (!defined('LOG_FORMAT')) {
    define('LOG_FORMAT', getenv('LOG_FORMAT') ?: 'json');
}
if (!defined('LOG_MAX_FILE_BYTES')) {
    define('LOG_MAX_FILE_BYTES', (int) (getenv('LOG_MAX_FILE_BYTES') ?: 10485760));
}
if (!defined('LOG_MAX_FILES')) {
    define('LOG_MAX_FILES', (int) (getenv('LOG_MAX_FILES') ?: 5));
}

// Security middleware defaults
if (!defined('SECURITY_HEADERS_ENABLED')) {
    define('SECURITY_HEADERS_ENABLED', getenv('SECURITY_HEADERS_ENABLED') !== false ? getenv('SECURITY_HEADERS_ENABLED') : '1');
}
if (!defined('API_MAX_BODY_BYTES')) {
    define('API_MAX_BODY_BYTES', (int) (getenv('API_MAX_BODY_BYTES') ?: 1048576));
}
if (!defined('API_TIMEOUT_SECONDS')) {
    define('API_TIMEOUT_SECONDS', (int) (getenv('API_TIMEOUT_SECONDS') ?: 15));
}

// Updater command hardening defaults
if (!defined('CORIANDER_UPDATER_ENABLED')) {
    define('CORIANDER_UPDATER_ENABLED', getenv('CORIANDER_UPDATER_ENABLED') !== false ? getenv('CORIANDER_UPDATER_ENABLED') : '1');
}
if (!defined('CORIANDER_UPDATER_ALLOW_PRODUCTION')) {
    define('CORIANDER_UPDATER_ALLOW_PRODUCTION', getenv('CORIANDER_UPDATER_ALLOW_PRODUCTION') !== false ? getenv('CORIANDER_UPDATER_ALLOW_PRODUCTION') : '0');
}
if (!defined('CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR')) {
    define('CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR', (int) (getenv('CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR') ?: 5));
}

// Check if the database.php file exists in the config folder
$databaseConfigFile = PROJECT_ROOT . '/config/database.php';

if (file_exists($databaseConfigFile)) {
    // Include the database configuration file
    include_once $databaseConfigFile;
}

