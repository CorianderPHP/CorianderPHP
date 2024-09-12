<?php

/**
 * CorianderPHP Autoloader
 * 
 * This autoloader dynamically loads PHP classes in the CorianderPHP framework based on the PSR-4 standard.
 * The autoloader automatically resolves and includes class files by mapping namespaces to directory structures.
 * 
 * It supports two primary class paths:
 * 
 * 1. **Core Framework Modules** (`core/` directory):
 *    - The `core/` directory contains all the essential modules required for the framework to function.
 *    - For example, the class `CorianderCore\Console\CommandHandler` will be mapped to:
 *      `CorianderCore/core/Console/CommandHandler.php`
 * 
 * 2. **User-Defined Modules** (`modules/` directory):
 *    - The `modules/` directory holds any user-defined modules that extend or modify the framework's behavior.
 *    - For example, the class `CustomModule\Google\GoogleSSO` will be mapped to:
 *      `CorianderCore/modules/CustomModule/Google/GoogleSSO.php`
 * 
 * ### Functionality
 * 
 * The autoloader follows these steps:
 * 
 * - Strips the `CorianderCore\` prefix from class names (if applicable) to align with the file structure.
 * - Converts the namespace into a file path by replacing namespace separators (`\`) with directory separators (`/`).
 * - Searches through the core and modules directories to locate the correct file for the class.
 * - If the class file is found, it is included. If not, an exception is thrown with an error message.
 * 
 * @param string $class The fully-qualified class name (namespace included).
 * @throws Exception If the class file cannot be located in any of the directories.
 */

spl_autoload_register(function ($class) {
    /**
     * Base directories to search for class files.
     * 
     * - `core/`: Contains core framework modules.
     * - `modules/`: Contains user-defined or external modules.
     */
    $baseDirs = [
        PROJECT_ROOT . '/CorianderCore/core/',    // Core framework modules directory
        PROJECT_ROOT . '/CorianderCore/modules/', // External user-defined modules directory
    ];

    /**
     * Convert the namespace to a relative file path.
     * 
     * 1. Remove the "CorianderCore\" prefix from class names.
     * 2. Replace the namespace separator (`\`) with the directory separator (`/`).
     * 3. Append the `.php` file extension.
     * 
     * Example: `CorianderCore\Console\CommandHandler` becomes `Console/CommandHandler.php`.
     * 
     * @var string $relativeClassPath The relative file path derived from the class name.
     */
    $relativeClassPath = str_replace('CorianderCore\\', '', $class);
    $relativeClassPath = str_replace('\\', '/', $relativeClassPath) . '.php';

    // Search through the defined base directories for the class file
    foreach ($baseDirs as $baseDir) {
        $file = $baseDir . $relativeClassPath;

        // If the class file is found, require it
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // If the class file isn't found, throw an exception
    throw new Exception("Class {$class} not found in any directory. Looked in: " . implode(', ', $baseDirs));
});
