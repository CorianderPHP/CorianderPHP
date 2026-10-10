<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console;

/**
 * Prints CLI messages with ampersand style codes and optional terminal colors.
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
    const COLOR_DARK_GRAY = "\033[90m";
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
     *  - &8: Dark Gray
     *  - &l: Bold
     *  - &u: Underline
     *  - &r: Reset formatting
     * 
     */
    public static function print(string $message): void
    {
        $message = '&7' . $message;

        if (!self::supportsAnsi()) {
            $plainMessage = preg_replace('/&[423e78lur]/', '', $message) ?? $message;
            echo $plainMessage . PHP_EOL;
            return;
        }

        $formattedMessage = str_replace([
            '&4', '&2', '&3', '&e', '&7', '&8', '&l', '&u', '&r'
        ], [
            self::COLOR_RED, self::COLOR_GREEN, self::COLOR_CYAN, self::COLOR_YELLOW, self::COLOR_GRAY, self::COLOR_DARK_GRAY, self::STYLE_BOLD, self::STYLE_UNDERLINE, self::STYLE_RESET
        ], $message);

        echo $formattedMessage . self::STYLE_RESET . PHP_EOL;
    }

    /**
     * Detect whether ANSI styling should be emitted for current stdout.
     */
    private static function supportsAnsi(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        if (getenv('FORCE_COLOR') !== false || getenv('CLICOLOR_FORCE') === '1') {
            return true;
        }

        if (!defined('STDOUT')) {
            return false;
        }

        if (function_exists('stream_isatty') && @stream_isatty(STDOUT)) {
            return true;
        }

        if (function_exists('posix_isatty') && @posix_isatty(STDOUT)) {
            return true;
        }

        if (DIRECTORY_SEPARATOR === '\\' && function_exists('sapi_windows_vt100_support')) {
            return @sapi_windows_vt100_support(STDOUT);
        }

        return false;
    }

    public static function hr(): void
    {
        self::print("&8-----------------------------------------");
    }
}
