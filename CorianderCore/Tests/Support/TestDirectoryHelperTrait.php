<?php
declare(strict_types=1);

namespace CorianderCore\Tests\Support;

trait TestDirectoryHelperTrait
{
    protected function createTemporaryDirectory(string $prefix): string
    {
        $directory = PROJECT_ROOT . '/CorianderCore/tests/' . $prefix . '_' . bin2hex(random_bytes(4));
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return $directory;
    }

    protected function deleteDirectory(string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            return;
        }

        $items = scandir($dirPath);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dirPath . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (file_exists($path)) {
                @unlink($path);
            }
        }

        @rmdir($dirPath);
    }
}
