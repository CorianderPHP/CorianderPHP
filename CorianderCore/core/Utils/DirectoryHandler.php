<?php

namespace CorianderCore\Core\Utils;

/**
 * The DirectoryHandler class provides utilities for working with directories.
 * It includes methods for creating directories and recursively deleting directories with their contents.
 */
class DirectoryHandler
{
    /**
     * Recursively delete a directory and its contents.
     *
     * This method deletes all files and subdirectories within the specified directory,
     * and then removes the directory itself.
     *
     * @param string $dirPath The path to the directory to delete.
     */
    public static function deleteDirectory(string $dirPath): void
    {
        // If the directory doesn't exist, exit the method.
        if (!is_dir($dirPath)) {
            return;
        }

        // Get all files and subdirectories within the directory, excluding '.' and '..'.
        $files = array_diff(scandir($dirPath), ['.', '..']);

        // Recursively delete files and subdirectories.
        foreach ($files as $file) {
            $filePath = "$dirPath/$file";
            // If it's a directory, recurse into it. Otherwise, delete the file.
            is_dir($filePath) ? self::deleteDirectory($filePath) : unlink($filePath);
        }

        // Remove the directory itself after its contents have been deleted.
        rmdir($dirPath);
    }

    /**
     * Create a new directory if it doesn't exist.
     * 
     * This method attempts to create a new directory with the specified path.
     * If the directory cannot be created, an exception is thrown.
     *
     * @param string $viewPath The path to the directory to be created.
     * @throws \Exception If the directory cannot be created.
     */
    public static function createDirectory(string $viewPath): void
    {
        try {
            // Attempt to create the directory with 0755 permissions. If it fails and the directory doesn't exist, throw an error.
            if (!mkdir($viewPath, 0755, true) && !is_dir($viewPath)) {
                throw new \Exception("Error: Failed to create directory '{$viewPath}'.");
            }
        } catch (\Exception $e) {
            // Rethrow the exception with an additional error message.
            throw new \Exception("Error: Unable to create view directory. " . $e->getMessage());
        }
    }
}
