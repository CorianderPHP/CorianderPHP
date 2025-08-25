<?php

namespace CorianderCore\Core\Router\Services;

/**
 * Manages caching of controller class paths using a PHP file for OPcache benefits.
 */
class ControllerCacheService
{
    /**
     * Path to the controller cache file.
     */
    private string $cacheFile;

    /**
     * Cached controller class to file mappings.
     *
     * @var array<string, string>
     */
    private array $cache = [];

    public function __construct(string $cacheFile = PROJECT_ROOT . '/cache/controllers.php')
    {
        $this->cacheFile = $cacheFile;
        if (file_exists($cacheFile)) {
            $data = require $cacheFile;
            if (is_array($data)) {
                $this->cache = $data;
            }
        }
    }

    /**
     * Determine if a controller exists in the cache.
     */
    public function has(string $controllerClass): bool
    {
        return isset($this->cache[$controllerClass]);
    }

    /**
     * Get the cached file path for a controller class.
     */
    public function get(string $controllerClass): ?string
    {
        return $this->cache[$controllerClass] ?? null;
    }

    /**
     * Build the controller cache by scanning the controllers directory.
     *
     * @param string $controllersDir Directory containing controller classes.
     */
    public function build(string $controllersDir = PROJECT_ROOT . '/src/Controllers'): void
    {
        $controllers = [];
        if (is_dir($controllersDir)) {
            foreach (glob($controllersDir . '/*Controller.php') as $file) {
                $class = 'Controllers\\' . basename($file, '.php');
                $controllers[$class] = $file;
            }
        }

        if (!is_dir(dirname($this->cacheFile))) {
            mkdir(dirname($this->cacheFile), 0777, true);
        }

        $content = "<?php\nreturn " . var_export($controllers, true) . ";\n";
        file_put_contents($this->cacheFile, $content);
        $this->cache = $controllers;
    }

    /**
     * Clear the controller cache by deleting the cache file.
     */
    public function clear(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        $this->cache = [];
    }
}
