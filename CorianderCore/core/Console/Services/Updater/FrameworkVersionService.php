<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services\Updater;

class FrameworkVersionService
{
    private string $projectRoot;
    private string $versionFile;

    public function __construct(?string $projectRoot = null, string $versionFile = 'CorianderCore/VERSION')
    {
        $this->projectRoot = $projectRoot ?? (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 6));
        $this->versionFile = $versionFile;
    }

    public function getLocalVersion(): string
    {
        $path = $this->projectRoot . '/' . ltrim($this->versionFile, '/');
        if (!file_exists($path)) {
            return '0.0.0';
        }

        $version = trim((string) file_get_contents($path));
        return $version !== '' ? $version : '0.0.0';
    }

    public function isUpdateAvailable(string $localVersion, string $latestVersion): bool
    {
        return version_compare($this->normalize($latestVersion), $this->normalize($localVersion), '>');
    }

    private function normalize(string $version): string
    {
        $normalized = trim($version);
        return ltrim($normalized, "vV \t\n\r\0\x0B");
    }
}

