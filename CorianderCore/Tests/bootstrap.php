<?php
declare(strict_types=1);

function corianderDeleteDirectoryRecursive(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            corianderDeleteDirectoryRecursive($path);
        } elseif (file_exists($path)) {
            @unlink($path);
        }
    }

    @rmdir($directory);
}

function corianderCleanupTestTempArtifacts(string $testsRoot): void
{
    $tmpEntries = glob($testsRoot . '/_tmp*', GLOB_NOSORT) ?: [];
    foreach ($tmpEntries as $entry) {
        if (is_dir($entry)) {
            corianderDeleteDirectoryRecursive($entry);
        } elseif (is_file($entry)) {
            @unlink($entry);
        }
    }
}

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 2));
}

$testsRoot = PROJECT_ROOT . '/CorianderCore/Tests';
corianderCleanupTestTempArtifacts($testsRoot);

$config = PROJECT_ROOT . '/config/config.php';
if (file_exists($config)) {
    require_once $config;
}

if (!defined('DB_TYPE')) {
    define('DB_TYPE', 'sqlite');
}

if (!defined('DB_NAME')) {
    $testDbPath = $testsRoot . '/_tmp_test.sqlite';
    $testDbDir = dirname($testDbPath);
    if (!is_dir($testDbDir)) {
        mkdir($testDbDir, 0777, true);
    }
    define('DB_NAME', $testDbPath);
}

// Prevent updater guard from rate-limiting repeated test invocations.
putenv('CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR=0');
putenv('CORIANDER_UPDATER_ALLOW_PRODUCTION=1');

$composerAutoload = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

$coreAutoload = PROJECT_ROOT . '/CorianderCore/autoload.php';
if (file_exists($coreAutoload)) {
    require_once $coreAutoload;
}

register_shutdown_function(static function () use ($testsRoot): void {
    corianderCleanupTestTempArtifacts($testsRoot);
});
