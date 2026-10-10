<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use PHPUnit\Framework\TestCase;

class ApacheAccessRulesTest extends TestCase
{
    public function testPrivatePathsAreDeniedBeforeRouting(): void
    {
        $configuration = (string) file_get_contents(PROJECT_ROOT . '/.htaccess');
        preg_match_all('/^RewriteRule (\S+) - \[F,L(?:,NC)?\]$/m', str_replace("\r", '', $configuration), $matches);
        $patterns = $matches[1];
        $this->assertNotEmpty($patterns);
        $this->assertLessThan(strpos($configuration, 'DirectoryIndex'), strpos($configuration, '[F,L]'));

        foreach (['.env', '.env.production', '.git/config', 'assets/.secret', 'CorianderCore/VERSION', 'src/Controllers/AdminController.php', 'config/database.php', 'vendor/autoload.php', 'database/users.sqlite', 'backups/coriander/a.php.bak.1', 'cache/controllers.php', 'logs/app.log', 'public/public_views/admin/index.php', 'public/routes.php', 'composer.lock', 'coriander'] as $path) {
            $this->assertTrue($this->isDenied($path, $patterns), $path);
        }

        foreach (['public/index.php', 'public/assets/css/output.css', 'public/assets/js/app.js', 'public/assets/img/logo.svg', 'home', 'api/users'] as $path) {
            $this->assertFalse($this->isDenied($path, $patterns), $path);
        }
    }

    private function isDenied(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('~' . $pattern . '~i', $path) === 1) {
                return true;
            }
        }
        return false;
    }
}
