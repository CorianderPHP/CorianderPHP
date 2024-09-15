<?php

namespace CorianderCore\Console;

/**
 * The ConsoleOutput class handles console output with custom color
 * codes and styles similar to Minecraft chat formatting.
 */
class ConsoleOutput
{
    // ANSI color codes for terminal output
    const COLOR_RED = "\033[31m";
    const COLOR_GREEN = "\033[32m";
    const COLOR_YELLOW = "\033[33m";
    const COLOR_BLUE = "\033[34m";
    const COLOR_CYAN = "\033[36m";
    const COLOR_GRAY = "\033[37m";
    const COLOR_RESET = "\033[0m";
    
    // ANSI style codes
    const STYLE_BOLD = "\033[1m";
    const STYLE_UNDERLINE = "\033[4m";
    const STYLE_RESET = "\033[0m";

    /**
     * Prints a message to the console with Minecraft-like chat formatting codes.
     * Supported codes:
     *  - &4: Red
     *  - &2: Green
     *  - &3: Cyan
     *  - &e: Yellow
     *  - &7: Gray
     *  - &l: Bold
     *  - &u: Underline
     *  - &r: Reset formatting
     * 
     * @param string $message The message with color/style codes.
     */
    public static function print($message)
    {
        $message = '&7' . $message;

        // Replace color codes with corresponding ANSI codes
        $formattedMessage = str_replace([
            '&4', '&2', '&3', '&e', '&7', '&l', '&u', '&r'
        ], [
            self::COLOR_RED, self::COLOR_GREEN, self::COLOR_CYAN, self::COLOR_YELLOW, self::COLOR_GRAY, self::STYLE_BOLD, self::STYLE_UNDERLINE, self::STYLE_RESET
        ], $message);

        // Output the formatted message to the console
        echo $formattedMessage . self::STYLE_RESET . PHP_EOL;
    }

    /**
     * Prints a horizontal rule (line of dashes).
     */
    public static function hr()
    {
        self::print("-----------------------------------------");
    }
}
