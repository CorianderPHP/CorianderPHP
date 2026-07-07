<?php
declare(strict_types=1);

namespace CorianderCore\Core\Bootstrap;

final class EnvLoader
{
    public static function load(string $projectRoot, bool $createFromExample = true, bool $overwrite = false): void
    {
        $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $envPath = $projectRoot . '/.env';
        $examplePath = $projectRoot . '/.env-example';

        if (!is_file($envPath) && $createFromExample && is_file($examplePath)) {
            @copy($examplePath, $envPath);
        }

        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            self::loadLine($line, $overwrite);
        }
    }

    private static function loadLine(string $line, bool $overwrite): void
    {
        $line = trim(ltrim($line, "\xEF\xBB\xBF"));
        if ($line === '' || str_starts_with($line, '#')) {
            return;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $separatorPosition = strpos($line, '=');
        if ($separatorPosition === false) {
            return;
        }

        $name = trim(substr($line, 0, $separatorPosition));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            return;
        }

        if (!$overwrite && self::hasEnvironmentValue($name)) {
            return;
        }

        $value = self::parseValue(substr($line, $separatorPosition + 1));

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private static function hasEnvironmentValue(string $name): bool
    {
        return getenv($name) !== false || array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER);
    }

    private static function parseValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        $last = substr($value, -1);

        if ($first === '"' && $last === '"') {
            return strtr(substr($value, 1, -1), [
                '\\n' => "\n",
                '\\r' => "\r",
                '\\t' => "\t",
                '\\"' => '"',
                '\\\\' => '\\',
            ]);
        }

        if ($first === "'" && $last === "'") {
            return str_replace("\\'", "'", substr($value, 1, -1));
        }

        return self::stripInlineComment($value);
    }

    private static function stripInlineComment(string $value): string
    {
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            if ($value[$index] === '#' && ($index === 0 || ctype_space($value[$index - 1]))) {
                return rtrim(substr($value, 0, $index));
            }
        }

        return rtrim($value);
    }
}
