<?php

/**
 * CorianderPHP Autoloader
 *
 * Provides PSR-4 compliant class loading using a simple namespace-to-directory map.
 *
 * Supported namespace prefixes:
 * - `CorianderCore\\Core\\`    ? `/CorianderCore/core/`
 * - `CorianderCore\\Modules\\` ? `/CorianderCore/modules/`
 * - `CorianderCore\\Tests\\`   ? `/CorianderCore/Tests/`
 *
 * @param string $class Fully-qualified class name
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
