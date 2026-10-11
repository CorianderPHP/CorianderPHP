<?php
declare(strict_types=1);
namespace CorianderCore\Core\Console\Commands\Make\Sitemap;

use CorianderCore\Core\Console\{CommandExitCode, ConsoleOutput};
use CorianderCore\Core\Router\{RouteMap, SafePath};

class MakeSitemap
{
    public function __construct(private string $directory = PROJECT_ROOT . '/src/Views') {}

    public function execute(): int
    {
        $name = 'sitemap.xml.get.php';
        try {
            if (file_exists($this->directory . '/' . $name)) {
                throw new \RuntimeException('Sitemap already exists at ' . $this->directory . '/' . $name);
            }
            (new RouteMap())->validateNewRoute($this->directory, $name);
            $file = SafePath::destination($this->directory, $name);
            $template = file_get_contents(PROJECT_ROOT . '/CorianderCore/core/Console/Commands/Make/Sitemap/templates/sitemap.php');
            if ($template === false || file_put_contents($file, $template) === false) {
                throw new \RuntimeException('Failed to write sitemap endpoint');
            }
            ConsoleOutput::print('Success: Sitemap created successfully at ' . $file);
            return CommandExitCode::SUCCESS;
        } catch (\InvalidArgumentException | \RuntimeException $error) {
            ConsoleOutput::print('Error: ' . $error->getMessage());
            return CommandExitCode::FAILURE;
        }
    }
}
