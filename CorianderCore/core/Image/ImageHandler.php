<?php
declare(strict_types=1);

/*
 * ImageHandler converts images to WebP and builds responsive <picture> tags
 * for efficient image delivery.
 */

namespace CorianderCore\Core\Image;

use CorianderCore\Core\Logging\StaticLoggerTrait;

/**
 * Handles image conversion and rendering for PNG, JPG, and JPEG files.
 * Converts images to WebP format with a customizable quality setting,
 * and generates a <picture> tag with the necessary <source> elements
 * for WebP and original image formats, along with customizable CSS classes
 * and alt attributes. All issues encountered during conversion are reported
 * through an injected PSR-3 logger.
 */
class ImageHandler
{
    use StaticLoggerTrait;

    /**
     * Path to the base image directory.
     */
    private static string $imageDir = PROJECT_ROOT;

    /**
     * Subdirectory for storing WebP images.
     */
    private static string $webpDir = 'webp/';

    /**
     * Renders a picture tag with WebP and original format support.
     *
     * @param string $imagePath The path to the original image (relative to image directory).
     * @param string $altText The alt attribute for the img tag.
     * @param string $pictureClass Custom CSS classes for the picture tag.
     * @param string $imgClass Custom CSS classes for the img tag.
     * @param int $quality The quality for the WebP conversion (default: 80).
     * @return string The generated HTML for the picture element.
     */
    public static function render(string $imagePath, string $altText = '', string $pictureClass = '', string $imgClass = '', int $quality = 80): string
    {
        // Convert the image to WebP format if it doesn't already exist
        $webpPath = self::convertToWebP($imagePath, $quality);

        // Generate the paths for the original image and WebP image
        $fullImagePath = self::$imageDir . $imagePath;

        // Check if the original image exists to get dimensions; otherwise, set default dimensions
        $imageSize = @getimagesize($fullImagePath);
        $width = $height = '';
        if ($imageSize !== false) {
            [$width, $height] = $imageSize;
        }

        // Prepare the HTML for the <picture> element
        $pictureHTML = "<picture class=\"$pictureClass\">";
        
        if ($webpPath) {
            $webpRelativePath = self::getRelativePath($webpPath);
            $pictureHTML .= "<source srcset=\"$webpRelativePath\" type=\"image/webp\" />";
        }
        
        $originalRelativePath = self::getRelativePath($fullImagePath);
        $pictureHTML .= "<source srcset=\"$originalRelativePath\" type=\"image/" . pathinfo($imagePath, PATHINFO_EXTENSION) . "\" />";
        $pictureHTML .= "<img alt='$altText' width=\"$width\" height=\"$height\" class=\"$imgClass\" src=\"$originalRelativePath\" />";
        
        $pictureHTML .= "</picture>";
        
        return $pictureHTML;
    }

    /**
     * Converts a given image to WebP format with the specified quality.
     *
     * @param string $imagePath The path to the original image (relative to image directory).
     * @param int $quality The quality for the WebP conversion.
     * @return string|false The path to the WebP image if successful, or false on failure.
     */
    public static function convertToWebP(string $imagePath, int $quality = 80): string|false
    {
        $fullImagePath = self::$imageDir . $imagePath;
        
        if (!file_exists($fullImagePath)) {
            self::getLogger()->warning('Image not found: ' . $fullImagePath);
            return false;
        }

        $webpPath = self::getWebPPath($imagePath, $quality);

        // If WebP file already exists, no need to convert
        if (file_exists($webpPath)) {
            return $webpPath;
        }

        // Create the WebP directory if it doesn't exist
        $webpDirPath = dirname($webpPath);
        if (!is_dir($webpDirPath)) {
            mkdir($webpDirPath, 0755, true);
        }

        $imageInfo = getimagesize($fullImagePath);
        if ($imageInfo === false) {
            self::getLogger()->warning('Invalid image file: ' . $fullImagePath);
            return false;
        }

        $mimeType = $imageInfo['mime'];
        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($fullImagePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($fullImagePath);
                break;
            default:
                self::getLogger()->warning('Unsupported image type: ' . $mimeType);
                return false;
        }

        if ($image === false) {
            self::getLogger()->error('Failed to create image resource from: ' . $fullImagePath);
            return false;
        }

        // Convert to WebP with specified quality
        $conversionResult = imagewebp($image, $webpPath, $quality);
        imagedestroy($image);

        if ($conversionResult === false) {
            self::getLogger()->error('Failed to convert image to WebP: ' . $fullImagePath);
            return false;
        }

        return $webpPath;
    }

    /**
     * Returns the WebP image path based on the original image path,
     * placing the WebP image inside the 'webp' subdirectory within the same directory.
     * The quality value is included in the filename, e.g., 'image_80.webp'.
     *
     * @param string $imagePath The path to the original image (relative to image directory).
     * @param int $quality The quality for the WebP conversion.
     * @return string The path to the WebP image.
     */
    private static function getWebPPath(string $imagePath, int $quality): string
    {
        $imagePathInfo = pathinfo($imagePath);
        return self::$imageDir . $imagePathInfo['dirname'] . '/' . self::$webpDir . $imagePathInfo['filename'] . "_{$quality}.webp";
    }

    /**
     * Returns the relative path of the image from the base directory.
     *
     * @param string $fullPath The full path to the image.
     * @return string The relative path.
     */
    private static function getRelativePath(string $fullPath): string
    {
        return str_replace(self::$imageDir, '', $fullPath);
    }
}

