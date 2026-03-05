<?php
declare(strict_types=1);

namespace CorianderCore\Core\Bootstrap;

class TimezoneBootstrap
{
    /**
     * Apply app timezone from environment value when valid.
     */
    public static function applyFromEnvironment(?string $timezone): bool
    {
        if (!is_string($timezone) || $timezone === '') {
            return false;
        }

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            return false;
        }

        date_default_timezone_set($timezone);
        return true;
    }
}
