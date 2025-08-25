<?php

namespace CorianderCore\Core\Router;

/**
 * Utility class for formatting controller and route names.
 *
 * Converts URI segments to PascalCase to match controller class naming conventions.
 *
 * @package CorianderCore\Core\Router
 */
class NameFormatter
{
    /**
     * Converts a string like 'user-profile' or 'user_profile' to 'UserProfile'.
     *
     * @param string $name The raw controller or route segment.
     * @return string PascalCase formatted string.
     */
    public static function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }
}
