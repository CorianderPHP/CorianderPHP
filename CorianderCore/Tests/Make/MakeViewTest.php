<?php

namespace CorianderCore\Tests\Make;

use PHPUnit\Framework\TestCase;
use CorianderCore\Core\Console\Commands\Make\View\MakeView;
use CorianderCore\Core\Utils\DirectoryHandler;

class MakeViewTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testGeneratedViewRendersWithoutOpeningSession(): void
    {
        ob_start();
        try {
            $this->assertSame(0, $this->makeView->execute(['PublicPage']));
        } finally { ob_end_clean(); }
        $response = (new \CorianderCore\Core\Router\ViewRenderer(self::$testPath))->response('public-page');
        $html = (string) $response->getBody();
        $this->assertStringContainsString('<h1>public-page</h1>', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('<!--', $html);
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    /** @var MakeView */
    protected $makeView;

    /** @var string */
    protected static $testPath;

    public static function setUpBeforeClass(): void
    {
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 3));
        }

        self::$testPath = PROJECT_ROOT . "/CorianderCore/Tests/_tmp/";
    }

    protected function setUp(): void
    {
        if (!is_dir(self::$testPath)) {
            mkdir(self::$testPath, 0777, true);
        }

        $this->makeView = new MakeView(self::$testPath);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_dir(self::$testPath)) {
            DirectoryHandler::deleteDirectory(self::$testPath);
        }
    }

    public function testCreateViewSuccessfully()
    {
        $viewName = "newview";

        $this->makeView->execute([$viewName]);

        $this->assertFileExists(self::$testPath . 'newview.php');
        $this->assertFileDoesNotExist(self::$testPath . 'newview/metadata.php');
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/View 'newview' created successfully at/");
    }

    public function testViewAlreadyExists()
    {
        $viewName = "newview";

        $this->makeView->execute([$viewName]);

        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/'newview' already exists./");
    }

    public function testNoViewNameProvided()
    {
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/Please specify a view name./");

        $this->makeView->execute([]);
    }

    public function testViewNameFormatting()
    {
        $viewName = "AdminUser";

        $this->makeView->execute([$viewName]);

        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/View 'admin-user' created successfully at /");
    }
}
