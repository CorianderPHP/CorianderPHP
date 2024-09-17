<?php

namespace CorianderCore\Console\Services;

class PdoDriverService
{
    /**
     * Get available PDO drivers.
     *
     * @return array
     */
    public function getAvailableDrivers(): array
    {
        return \PDO::getAvailableDrivers();
    }
}
