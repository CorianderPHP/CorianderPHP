<?php
declare(strict_types=1);

namespace CorianderCore\Core\Bootstrap;

class TimezoneBootstrap
{
    /**
     * @var array<string, bool>|null
     */
    private static ?array $timezoneIndex = null;

    /**
     * Apply app timezone from environment value when valid.
     */
    public static function applyFromEnvironment(?string $timezone): bool
    {
        if (!is_string($timezone) || $timezone === '') {
            return false;
        }

        if (self::$timezoneIndex === null) {
            self::$timezoneIndex = array_fill_keys(timezone_identifiers_list(), true);
        }

        if (!isset(self::$timezoneIndex[$timezone])) {
            return false;
        }

        date_default_timezone_set($timezone);
        return true;
    }
}
