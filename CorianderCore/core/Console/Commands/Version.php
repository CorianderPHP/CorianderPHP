<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\ConsoleOutput;
use CorianderCore\Core\Console\Services\Updater\FrameworkVersionService;

class Version
{
    private FrameworkVersionService $versionService;

    public function __construct(?FrameworkVersionService $versionService = null)
    {
        $this->versionService = $versionService ?? new FrameworkVersionService();
    }

    /**
     * @param array<int, string> $args
     */
    public function execute(array $args = []): void
    {
        $version = $this->versionService->getLocalVersion();
        ConsoleOutput::print('&lFramework version:&r &2' . $version);
    }
}
