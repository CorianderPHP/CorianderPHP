<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/config/config.php';
require_once PROJECT_ROOT . '/CorianderCore/autoload.php';
$discovery = new class($argv[3]) extends \CorianderCore\Core\Router\RouteMap {
    public function __construct(private string $marker) {}
    public function discover(string $directory): array
    {
        file_put_contents($this->marker, "build\n", FILE_APPEND | LOCK_EX);
        usleep(250_000);
        return parent::discover($directory);
    }
};
$map = (new \CorianderCore\Core\Router\RouteCache($argv[1], $argv[2], true, 30, $discovery))->get();
echo isset($map['static']['']) ? 'complete' : 'incomplete';
