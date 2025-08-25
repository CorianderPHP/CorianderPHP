<?php

namespace CorianderCore\Tests;

use Error;
use PHPUnit\Framework\TestCase;

class AutoloaderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Define the PROJECT_ROOT constant if it isn't already defined.
        // This ensures that any path-related logic in the autoloader can resolve correctly.
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
    }

    /**
     * Test that a core class is correctly autoloaded.
     *
     * This test checks if the autoloader successfully loads core classes,
     * ensuring that required classes (like CommandHandler) are available when needed.
     */
    public function testCanLoadCoreClass()
    {
        // Assert that the CommandHandler class exists and is autoloaded correctly
        $this->assertTrue(class_exists('CorianderCore\Core\Console\CommandHandler'));
    }

    /**
     * Test that an Error is thrown when attempting to load a non-existent class.
     *
     * This test ensures that the autoloader behaves correctly when a class that doesn't
     * exist is requested. Since the autoloader no longer throws an Exception, but PHP
     * throws an Error when trying to instantiate a non-existent class, we expect an Error.
     */
    public function testThrowsErrorForNonExistentClass()
    {
        // Expect an Error to be thrown when trying to instantiate a class that does not exist
        $this->expectException(Error::class);

        // Attempt to instantiate a non-existent class by name, which should trigger an Error
        $className = 'NonExistentClass';
        new $className();
    }
}
