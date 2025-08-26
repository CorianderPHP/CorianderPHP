<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services;

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
