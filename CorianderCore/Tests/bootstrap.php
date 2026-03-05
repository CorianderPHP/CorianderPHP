<?php
declare(strict_types=1);

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 2));
}

$config = PROJECT_ROOT . '/config/config.php';
if (file_exists($config)) {
    require_once $config;
}

if (!defined('DB_TYPE')) {
    define('DB_TYPE', 'sqlite');
}

if (!defined('DB_NAME')) {
    $testDbPath = PROJECT_ROOT . '/CorianderCore/Tests/_tmp_test.sqlite';
    $testDbDir = dirname($testDbPath);
    if (!is_dir($testDbDir)) {
        mkdir($testDbDir, 0777, true);
    }
    define('DB_NAME', $testDbPath);
}

$composerAutoload = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

$coreAutoload = PROJECT_ROOT . '/CorianderCore/autoload.php';
if (file_exists($coreAutoload)) {
    require_once $coreAutoload;
}
