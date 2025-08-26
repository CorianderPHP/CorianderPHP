<?php

namespace CorianderCore\Core\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Simple PSR-3 compliant logger writing to configurable channels.
 *
 * Workflow:
 * 1. Read desired log channel and minimum level from environment variables
 *    `LOG_CHANNEL` and `LOG_LEVEL`.
 * 2. When a message is logged, discard it if its level is lower than the
 *    configured minimum level.
 * 3. Output formatted messages to the configured channel (stderr by default,
 *    a file path can also be provided).
 *
 * Channels supported: `stderr`, `stdout`, or an absolute/relative file path.
 */
class Logger extends AbstractLogger
{
    /**
     * Map of PSR log levels to numeric severity for comparison.
     *
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

    /**
     * @var string Chosen channel to output logs to.
     */
    private string $channel;

    /**
     * @var string Lowest log level to output.
     */
    private string $minLevel;

    /**
     * Creates a logger configured via environment variables.
     *
     * @param string|null $channel Optional log channel overriding `LOG_CHANNEL`.
     * @param string|null $minLevel Optional log level overriding `LOG_LEVEL`.
     */
    public function __construct(?string $channel = null, ?string $minLevel = null)
    {
        $this->channel = $channel ?? getenv('LOG_CHANNEL') ?: 'stderr';
        $this->minLevel = $minLevel ?? getenv('LOG_LEVEL') ?: LogLevel::WARNING;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string               $level   The severity level of the log.
     * @param string|Stringable    $message The log message.
     * @param array<string,mixed>  $context Additional context for interpolation.
     *
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        if (!isset($this->levels[$level])) {
            return;
        }
        if ($this->levels[$level] > $this->levels[$this->minLevel]) {
            return;
        }

        $message = $this->interpolate((string) $message, $context);
        $formatted = '[' . strtoupper($level) . '] ' . $message;

        switch ($this->channel) {
            case 'stdout':
                file_put_contents('php://stdout', $formatted . PHP_EOL, FILE_APPEND);
                break;
            case 'stderr':
                file_put_contents('php://stderr', $formatted . PHP_EOL, FILE_APPEND);
                break;
            default:
                error_log($formatted . PHP_EOL, 3, $this->channel);
        }
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string               $message Message with placeholders.
     * @param array<string,mixed>  $context Context values.
     *
     * @return string Interpolated message.
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }
        return strtr($message, $replace);
    }
}

