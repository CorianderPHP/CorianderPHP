<?php
declare(strict_types=1);

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
     * Shared cached controller mappings keyed by cache file path.
     *
     * @var array<string, array<string, string>>
     */
    private static array $cacheStore = [];

    /**
     * Singleton instance of the cache service.
     */
    private static ?self $instance = null;

    /**
     * Retrieve the singleton instance of the cache service.
     *
     * Ensures only one instance is used throughout the application.
     *
     * @return self Singleton instance of the service.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Create a new controller cache service.
     *
     * @param string $cacheFile Absolute path to the cache file storing controller mappings.
     */
    public function __construct(string $cacheFile = PROJECT_ROOT . '/cache/controllers.php')
    {
        $this->cacheFile = $cacheFile;

        if (!isset(self::$cacheStore[$cacheFile])) {
            if (file_exists($cacheFile)) {
                $data = require $cacheFile;
                self::$cacheStore[$cacheFile] = is_array($data) ? $data : [];
            } else {
                self::$cacheStore[$cacheFile] = [];
            }
        }
    }

    /**
     * Determine if a controller exists in the cache.
     */
    public function has(string $controllerClass): bool
    {
        return isset(self::$cacheStore[$this->cacheFile][$controllerClass]);
    }

    /**
     * Get the cached file path for a controller class.
     */
    public function get(string $controllerClass): ?string
    {
        return self::$cacheStore[$this->cacheFile][$controllerClass] ?? null;
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

        $cacheDirectory = dirname($this->cacheFile);
        if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0775, true) && !is_dir($cacheDirectory)) {
            throw new \RuntimeException('Unable to create controller cache directory.');
        }

        $content = "<?php\nreturn " . var_export($controllers, true) . ";\n";
        $temporaryFile = $this->cacheFile . '.tmp.' . bin2hex(random_bytes(8));

        if (file_put_contents($temporaryFile, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write controller cache file.');
        }

        if (!@rename($temporaryFile, $this->cacheFile)) {
            @unlink($temporaryFile);
            throw new \RuntimeException('Unable to replace controller cache file atomically.');
        }

        self::$cacheStore[$this->cacheFile] = $controllers;
    }

    /**
     * Clear the controller cache by deleting the cache file.
     */
    public function clear(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        self::$cacheStore[$this->cacheFile] = [];
    }
}

