<?php

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
     * This test checks if the autoloader successfully loads core classes,
     * ensuring that required classes (like CommandHandler) are available when needed.
     */
    public function testCanLoadCoreClass()
    {
        // Assert that the CommandHandler class exists and is autoloaded correctly
        $this->assertTrue(class_exists('CorianderCore\Console\CommandHandler'));
    }

    /**
     * Test that an exception is thrown when attempting to load a non-existent class.
     * This test ensures that the autoloader behaves correctly when a class that doesn't
     * exist is requested, throwing the appropriate exception.
     */
    public function testThrowsExceptionForNonExistentClass()
    {
        // Expect an Exception to be thrown when trying to load a class that does not exist
        $this->expectException(Exception::class);

        // Attempt to instantiate a non-existent class by name, which should trigger the autoloader and fail
        $className = 'NonExistentClass';
        new $className();
    }
}