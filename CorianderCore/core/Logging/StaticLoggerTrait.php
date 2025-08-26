<?php
declare(strict_types=1);

namespace CorianderCore\Core\Logging;

use Psr\Log\LoggerInterface;

/**
 * Provides reusable static logger handling for classes.
 *
 * Workflow:
 * 1. Host class "use"s this trait to gain `setLogger()` and `getLogger()`.
 * 2. `setLogger()` injects a PSR-3 logger for static contexts.
 * 3. `getLogger()` lazily instantiates the default {@see Logger} when none
 *    has been set, ensuring logging availability without manual setup.
 */
trait StaticLoggerTrait
{
    /**
     * @var LoggerInterface Logger used for reporting issues.
     */
    private static LoggerInterface $logger;

    /**
     * Injects a logger implementation.
     *
     * @param LoggerInterface $logger Logger instance.
     *
     * @return void
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Retrieves the logger, initialising a default one if necessary.
     *
     * @return LoggerInterface Logger instance.
     */
    protected static function getLogger(): LoggerInterface
    {
        if (!isset(self::$logger)) {
            self::$logger = new Logger();
        }
        return self::$logger;
    }
}
