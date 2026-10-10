<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

/** Data-only route maps, refreshed by incoming production requests. */
final class RouteCache
{
    private ?array $cached = null;
    private bool $production;
    private string $file;
    private RouteMap $discovery;

    public function __construct(
        private string $directory = PROJECT_ROOT . '/src/Routes',
        ?string $file = null,
        ?bool $production = null,
        private int $refreshInterval = 30,
        ?RouteMap $discovery = null,
    ) {
        $this->file = $file ?? PROJECT_ROOT . '/cache/routes.php';
        $this->production = $production ?? getenv('APP_ENV') === 'production';
        $this->discovery = $discovery ?? new RouteMap();
        if ($refreshInterval < 0) {
            throw new \InvalidArgumentException('Route refresh interval cannot be negative');
        }
    }

    /** @return array{static:array<string,array>,dynamic:list<array>,middleware:list<string>} */
    public function get(): array
    {
        if (!$this->production) {
            return $this->discovery->discover($this->directory);
        }
        $entry = $this->cached ?? $this->read();
        if ($this->fresh($entry)) {
            return $entry['map'];
        }
        $parent = dirname($this->file);
        if ((!is_dir($parent) && !@mkdir($parent, 0775, true)) || !is_writable($parent)) {
            return $this->discovery->discover($this->directory);
        }
        $lock = @fopen($this->file . '.lock', 'c');
        if ($lock === false) {
            return $this->discovery->discover($this->directory);
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                if ($entry !== null) {
                    return $entry['map'];
                }
                if (!flock($lock, LOCK_EX)) {
                    return $this->discovery->discover($this->directory);
                }
            }
            // A competing process may have published a complete map meanwhile.
            $entry = $this->read();
            if ($this->fresh($entry)) {
                return $entry['map'];
            }
            $map = $this->discovery->discover($this->directory);
            $entry = ['schema' => 1, 'directory' => $this->directory, 'built' => time(), 'map' => $map];
            $temporary = $this->file . '.' . bin2hex(random_bytes(8)) . '.tmp';
            try {
                $source = '<?php return ' . var_export($entry, true) . ';';
                if (@file_put_contents($temporary, $source) === strlen($source) && @rename($temporary, $this->file)) {
                    if (function_exists('opcache_invalidate')) {
                        opcache_invalidate($this->file, true);
                    }
                    $this->cached = $entry;
                }
            } finally {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
            return $map;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function clear(): void
    {
        $this->cached = null;
        if (is_file($this->file) && !unlink($this->file)) {
            throw new \RuntimeException('Cannot clear route cache');
        }
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->file, true);
        }
    }

    private function read(): ?array
    {
        if (!is_file($this->file)) {
            return null;
        }
        try {
            $entry = @include $this->file;
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($entry) || ($entry['schema'] ?? null) !== 1
            || ($entry['directory'] ?? null) !== $this->directory || !is_int($entry['built'] ?? null)
            || !is_array($entry['map']['static'] ?? null) || !is_array($entry['map']['dynamic'] ?? null)
            || !is_array($entry['map']['middleware'] ?? null)) {
            return null;
        }
        $this->cached = $entry;
        return $entry;
    }

    private function fresh(?array $entry): bool
    {
        return $entry !== null && $entry['built'] <= time() && time() - $entry['built'] < $this->refreshInterval;
    }
}
