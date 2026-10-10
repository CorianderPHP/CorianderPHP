<?php
declare(strict_types=1);

namespace CorianderCore\Core\Logging;

use Psr\Log\LoggerInterface;

/**
 * Provides an injectable static logger, creating the default logger on first use.
 */
trait StaticLoggerTrait
{
    private static LoggerInterface $logger;

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    protected static function getLogger(): LoggerInterface
    {
        if (!isset(self::$logger)) {
            self::$logger = new Logger();
        }
        return self::$logger;
    }
}
