<?php
declare(strict_types=1);

namespace CorianderCore\Core\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * PSR-3 logger with level filtering, optional JSON records, and file rotation.
 */
class Logger extends AbstractLogger
{
    /**
     * @var array<string,int>
     */
    private array $levels = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    private string $channel;
    private string $minLevel;
    private string $format;
    private int $maxFileBytes;
    private int $maxFiles;

    public function __construct(?string $channel = null, ?string $minLevel = null)
    {
        $this->channel = $channel ?? getenv('LOG_CHANNEL') ?: 'stderr';

        $configuredLevel = strtolower(trim($minLevel ?? getenv('LOG_LEVEL') ?: LogLevel::WARNING));
        $this->minLevel = isset($this->levels[$configuredLevel]) ? $configuredLevel : LogLevel::WARNING;

        $env = strtolower(trim((string) (getenv('APP_ENV') ?: 'production')));
        $configuredFormat = strtolower(trim((string) (getenv('LOG_FORMAT') ?: ($env === 'production' ? 'json' : 'line'))));
        $this->format = in_array($configuredFormat, ['json', 'line'], true) ? $configuredFormat : 'line';

        $this->maxFileBytes = max(1, (int) (getenv('LOG_MAX_FILE_BYTES') ?: 10_485_760));
        $this->maxFiles = max(1, (int) (getenv('LOG_MAX_FILES') ?: 5));
    }

    /**
     * @param string               $level
     * @param string|Stringable    $message
     * @param array<string,mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!is_string($level) || !isset($this->levels[$level])) {
            return;
        }
        if ($this->levels[$level] > $this->levels[$this->minLevel]) {
            return;
        }

        $message = $this->interpolate((string) $message, $context);
        $formatted = $this->formatRecord($level, $message, $context);

        $this->write($formatted . PHP_EOL);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function formatRecord(string $level, string $message, array $context): string
    {
        $timestamp = gmdate('c');

        if ($this->format === 'json') {
            $payload = [
                'timestamp' => $timestamp,
                'level' => $level,
                'message' => $message,
                'context' => $this->normalizeContext($context),
            ];

            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($json)) {
                return $json;
            }
        }

        return '[' . $timestamp . '][' . strtoupper($level) . '] ' . $message;
    }

    private function write(string $record): void
    {
        switch ($this->channel) {
            case 'stdout':
                file_put_contents('php://stdout', $record, FILE_APPEND);
                return;
            case 'stderr':
                file_put_contents('php://stderr', $record, FILE_APPEND);
                return;
            default:
                $this->rotateIfNeeded($this->channel, strlen($record));
                $directory = dirname($this->channel);
                if (!is_dir($directory)) {
                    @mkdir($directory, 0775, true);
                }

                file_put_contents($this->channel, $record, FILE_APPEND | LOCK_EX);
        }
    }

    private function rotateIfNeeded(string $path, int $incomingBytes): void
    {
        if (!file_exists($path)) {
            return;
        }

        $size = @filesize($path);
        if ($size === false || ($size + $incomingBytes) <= $this->maxFileBytes) {
            return;
        }

        $maxSuffix = $path . '.' . $this->maxFiles;
        if (file_exists($maxSuffix)) {
            @unlink($maxSuffix);
        }

        for ($index = $this->maxFiles - 1; $index >= 1; $index--) {
            $source = $path . '.' . $index;
            $destination = $path . '.' . ($index + 1);
            if (!file_exists($source)) {
                continue;
            }

            if (file_exists($destination)) {
                @unlink($destination);
            }

            @rename($source, $destination);
        }

        $first = $path . '.1';
        if (file_exists($first)) {
            @unlink($first);
        }

        @rename($path, $first);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $replace['{' . $key . '}'] = $this->stringifyValue($val);
        }

        return strtr($message, $replace);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $normalized[$key] = $this->normalizeContextValue($value);
        }

        return $normalized;
    }

    private function normalizeContextValue(mixed $value): mixed
    {
        if ($value instanceof Throwable) {
            return [
                'type' => $value::class,
                'message' => $value->getMessage(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
            ];
        }

        if (is_scalar($value) || $value === null || is_array($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return get_debug_type($value);
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value instanceof Throwable) {
            return $value->getMessage();
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($json)) {
            return $json;
        }

        return get_debug_type($value);
    }
}
