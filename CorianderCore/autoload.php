<?php

/**
 * Namespace map for application, framework, module and test classes:
 * - `CorianderCore\\Core\\`    -> `/CorianderCore/core/`
 * - `CorianderCore\\Modules\\` -> `/CorianderCore/modules/`
 * - `CorianderCore\\Tests\\`   -> `/CorianderCore/Tests/`
 */
spl_autoload_register(function (string $class): void {
    if (!defined('PROJECT_ROOT')) {
        define('PROJECT_ROOT', dirname(__DIR__));
    }

    static $composerLoaded = false;
    if (!$composerLoaded) {
        $composerAutoload = PROJECT_ROOT . '/vendor/autoload.php';
        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        }
        $composerLoaded = true;
    }

    $prefixes = [
        'App\\'                    => PROJECT_ROOT . '/src/',
        'CorianderCore\\Core\\'    => PROJECT_ROOT . '/CorianderCore/core/',
        'CorianderCore\\Modules\\' => PROJECT_ROOT . '/CorianderCore/modules/',
        'CorianderCore\\Tests\\'   => PROJECT_ROOT . '/CorianderCore/Tests/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            continue;
        }
        $relative = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
