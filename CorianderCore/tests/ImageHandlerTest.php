<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Image\ImageHandler;
use CorianderCore\Utils\DirectoryHandler;

/**
 * Class ImageHandlerTest
 *
 * This test class verifies the functionality of the ImageHandler class, 
 * including converting images to WebP format and rendering a <picture> 
 * element with WebP and original image sources.
 * 
 * Requirements:
 * - PHP GD extension must be enabled to run the tests.
 * - The test will skip with a message if the GD extension is not available.
 */
class ImageHandlerTest extends TestCase
{
    /**
     * @var string Path to the temporary directory for testing.
     */
    protected static $testPath;

    // Directories and file paths for testing
    private static $testImageDir;
    private static $testImagePath;
    private static $webpDir;
    private static $testImageFullPath;
    private static $testWebpFullPath;

    /**
     * setUpBeforeClass
     *
     * This method runs once before all tests in the class. It sets up the necessary
     * directory paths and ensures the required files are created. If GD is not available,
     * the test will be skipped.
     */
    public static function setUpBeforeClass(): void
    {
        // Define PROJECT_ROOT if it's not already defined
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }

        // Set the path to the temporary test directory
        self::$testPath = PROJECT_ROOT . "/CorianderCore/tests/_tmp";
    }

    
    protected function setUp(): void
    {
        // Check if GD extension is enabled
        if (!self::isGdEnabled()) {
            self::markTestSkipped(
                'The GD extension is not enabled. Please enable it in your php.ini file. Current php.ini: ' . php_ini_loaded_file()
            );
        }
        
        self::$testImageDir = self::$testPath . '/assets/';
        self::$testImagePath = '/CorianderCore/tests/_tmp/assets/test_image.png';
        self::$webpDir = 'webp/';
        self::$testImageFullPath = self::$testImageDir . 'test_image.png';
        self::$testWebpFullPath = self::$testImageDir . self::$webpDir . 'test_image_80.webp';

        // Create test image directory if it doesn't exist
        if (!is_dir(self::$testImageDir)) {
            mkdir(self::$testImageDir, 0755, true);
        }

        // Create a test image if it doesn't exist
        if (!file_exists(self::$testImageFullPath)) {
            $image = imagecreatetruecolor(100, 100);
            $backgroundColor = imagecolorallocate($image, 0, 0, 0); // Black background
            imagefilledrectangle($image, 0, 0, 100, 100, $backgroundColor);
            imagepng($image, self::$testImageFullPath);
            imagedestroy($image);
        }
    }
    
    /**
     * tearDownAfterClass
     *
     * This method runs once after all tests in the class have completed.
     * It cleans up the test environment by removing test files and directories.
     */
    public static function tearDownAfterClass(): void
    {
        // Cleanup: Remove test files and directories if they exist
        if (is_dir(self::$testPath)) {
            DirectoryHandler::deleteDirectory(self::$testPath); // Cleanup the temporary directory.
        }
    }

    /**
     * isGdEnabled
     *
     * Checks if the GD extension is enabled.
     *
     * @return bool
     */
    private static function isGdEnabled(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    /**
     * testConvertToWebP
     *
     * Tests the conversion of a PNG image to WebP format.
     * Verifies that the WebP image file is created successfully.
     */
    public function testConvertToWebp()
    {
        // Test conversion to WebP format
        ImageHandler::convertToWebP(self::$testImagePath, 80);

        $this->assertFileExists(
            self::$testImageDir . self::$webpDir . 'test_image_80.webp',
            'WebP image file was not created successfully.'
        );
    }

    /**
     * testRender
     *
     * Tests the rendering of a <picture> element with WebP and original image sources.
     * Verifies that the correct HTML structure is generated.
     */
    public function testRender()
    {
        // Test rendering the picture tag
        $html = ImageHandler::render(self::$testImagePath, 'Test Image', 'picture-class', 'img-class', 80);

        $this->assertStringContainsString('<picture class="picture-class">', $html, 'Picture tag was not rendered correctly.');
        $this->assertStringContainsString('<source srcset="/CorianderCore/tests/_tmp/assets/webp/test_image_80.webp" type="image/webp"', $html, 'WebP source tag was not rendered correctly.');
        $this->assertStringContainsString('<img alt=\'Test Image\' width="100" height="100" class="img-class" src="/CorianderCore/tests/_tmp/assets/test_image.png"', $html, 'Image tag was not rendered correctly.');
    }
}
