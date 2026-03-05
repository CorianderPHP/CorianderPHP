<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\ViewRenderer;
use PHPUnit\Framework\TestCase;

class ViewRendererTest extends TestCase
{
    private string $safeViewDir;
    private string $outsideViewDir;

    protected function setUp(): void
    {
        $this->safeViewDir = PROJECT_ROOT . '/public/public_views/security-test-' . bin2hex(random_bytes(4));
        $this->outsideViewDir = PROJECT_ROOT . '/public/private-test-' . bin2hex(random_bytes(4));

        if (!defined('REQUESTED_VIEW')) {
            define('REQUESTED_VIEW', 'security-test-view');
        }
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->safeViewDir);
        $this->deleteDirectory($this->outsideViewDir);
    }

    public function testRendersValidRelativeViewPath(): void
    {
        mkdir($this->safeViewDir, 0775, true);
        file_put_contents($this->safeViewDir . '/index.php', '<?php echo "SECURITY_VIEW_OK";');

        $renderer = new ViewRenderer();

        ob_start();
        $result = $renderer->render(basename($this->safeViewDir));
        $output = (string) ob_get_clean();

        $this->assertTrue($result);
        $this->assertStringContainsString('SECURITY_VIEW_OK', $output);
    }

    public function testRejectsDotSegmentTraversalPath(): void
    {
        mkdir($this->outsideViewDir, 0775, true);
        file_put_contents($this->outsideViewDir . '/index.php', '<?php echo "SHOULD_NOT_RENDER";');

        $renderer = new ViewRenderer();

        ob_start();
        $result = $renderer->render('../' . basename($this->outsideViewDir));
        $output = (string) ob_get_clean();

        $this->assertFalse($result);
        $this->assertSame('', $output);
    }

    public function testRejectsAbsolutePath(): void
    {
        $renderer = new ViewRenderer();
        $this->assertFalse($renderer->render('C:/temp/example'));
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (file_exists($path)) {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
