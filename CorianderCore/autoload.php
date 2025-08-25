<?php

/**
 * CorianderPHP Autoloader
 *
 * Provides PSR-4 compliant class loading using a simple namespace-to-directory map.
 *
 * Supported namespace prefixes:
 * - `CorianderCore\\Core\\`    → `/CorianderCore/core/`
 * - `CorianderCore\\Modules\\` → `/CorianderCore/modules/`
 * - `CorianderCore\\Tests\\`   → `/CorianderCore/tests/`
 *
 * @param string $class Fully-qualified class name
 */
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'CorianderCore\\Core\\'    => PROJECT_ROOT . '/CorianderCore/core/',
        'CorianderCore\\Modules\\' => PROJECT_ROOT . '/CorianderCore/modules/',
        'CorianderCore\\Tests\\'   => PROJECT_ROOT . '/CorianderCore/tests/',
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
