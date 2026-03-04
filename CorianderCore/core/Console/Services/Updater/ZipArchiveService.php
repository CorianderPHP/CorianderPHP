<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services\Updater;

use RuntimeException;
use ZipArchive;

final class ZipArchiveService
{
    public function extract(string $archivePath, string $destinationDirectory): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is required for framework updates.');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($archivePath);
        if ($openResult !== true) {
            throw new RuntimeException('Unable to open downloaded update archive.');
        }

        if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0775, true) && !is_dir($destinationDirectory)) {
            $zip->close();
            throw new RuntimeException('Unable to create update extraction directory.');
        }

        if (!$zip->extractTo($destinationDirectory)) {
            $zip->close();
            throw new RuntimeException('Unable to extract update archive.');
        }
        $zip->close();

        $entries = glob($destinationDirectory . '/*');
        if ($entries === false || count($entries) === 0) {
            throw new RuntimeException('Update archive extraction returned no files.');
        }

        foreach ($entries as $entry) {
            if (is_dir($entry)) {
                return $entry;
            }
        }

        throw new RuntimeException('Update archive has an unexpected structure.');
    }
}
