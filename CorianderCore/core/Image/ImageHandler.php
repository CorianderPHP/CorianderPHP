<?php
declare(strict_types=1);

/*
 * ImageHandler converts supported images to WebP and builds responsive <picture> tags
 * for efficient image delivery.
 */

namespace CorianderCore\Core\Image;

use CorianderCore\Core\Logging\StaticLoggerTrait;
use CorianderCore\Core\Support\PublicUrl;

/**
 * Handles image conversion and rendering for PNG, JPG, JPEG, WebP, and SVG files.
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
     * @param array<string, bool|int|float|string|null> $options Rendering options and optional img attributes.
     * @return string The generated HTML for the picture element.
     */
    public static function render(string $imagePath, array $options = []): string
    {
        $normalizedPath = self::normalizeImagePath($imagePath);
        if ($normalizedPath === null) {
            self::getLogger()->warning('Rejected unsafe image path: ' . $imagePath);
            return '';
        }

        $renderOptions = self::normalizeRenderOptions($options);

        $fullImagePath = self::resolveFullImagePath($normalizedPath);
        if ($fullImagePath === null) {
            self::getLogger()->warning('Unable to resolve image path: ' . $normalizedPath);
            return '';
        }

        $mimeType = self::getMimeType($normalizedPath, $fullImagePath);
        if ($mimeType === null) {
            self::getLogger()->warning('Unsupported image type: ' . $normalizedPath);
            return '';
        }

        $shouldConvert = $renderOptions['convert'] && self::isConvertibleMimeType($mimeType);
        $webpPath = $shouldConvert ? self::convertToWebP($normalizedPath, $renderOptions['quality']) : false;

        // Check if the original image exists to get dimensions; otherwise, set default dimensions
        $imageSize = @getimagesize($fullImagePath);
        $width = $height = '';
        if ($imageSize !== false) {
            [$width, $height] = $imageSize;
        }

        $safePictureClass = self::escapeHtmlAttribute($renderOptions['pictureClass']);
        $safeImgClass = self::escapeHtmlAttribute($renderOptions['imgClass']);
        $safeAltText = self::escapeHtmlAttribute($renderOptions['alt']);
        $safeAttributes = self::renderImageAttributes($options);

        $pictureHTML = "<picture class=\"{$safePictureClass}\">";

        if ($mimeType === 'image/webp') {
            $webpRelativePath = self::escapeHtmlAttribute(self::toPublicUrl($normalizedPath));
            $pictureHTML .= "<source srcset=\"{$webpRelativePath}\" type=\"image/webp\" />";
        } elseif ($webpPath) {
            $webpRelativePath = self::escapeHtmlAttribute(self::getWebPRelativePath($normalizedPath, $renderOptions['quality']));
            $pictureHTML .= "<source srcset=\"{$webpRelativePath}\" type=\"image/webp\" />";
        }

        $originalRelativePath = self::escapeHtmlAttribute(self::toPublicUrl($normalizedPath));
        $safeOriginalType = self::escapeHtmlAttribute($mimeType);
        $safeWidth = self::normalizeDimension($renderOptions['width'] ?? $width);
        $safeHeight = self::normalizeDimension($renderOptions['height'] ?? $height);

        if ($mimeType !== 'image/svg+xml' && $mimeType !== 'image/webp') {
            $pictureHTML .= "<source srcset=\"{$originalRelativePath}\" type=\"{$safeOriginalType}\" />";
        }

        $pictureHTML .= "<img alt=\"{$safeAltText}\" width=\"{$safeWidth}\" height=\"{$safeHeight}\" class=\"{$safeImgClass}\" src=\"{$originalRelativePath}\"{$safeAttributes} />";

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
        $normalizedPath = self::normalizeImagePath($imagePath);
        if ($normalizedPath === null) {
            self::getLogger()->warning('Rejected unsafe image path: ' . $imagePath);
            return false;
        }

        $fullImagePath = self::resolveFullImagePath($normalizedPath);
        if ($fullImagePath === null) {
            self::getLogger()->warning('Unable to resolve image path: ' . $normalizedPath);
            return false;
        }

        if (!file_exists($fullImagePath)) {
            self::getLogger()->warning('Image not found: ' . $fullImagePath);
            return false;
        }

        $webpPath = self::getWebPPath($normalizedPath, $quality);

        if (file_exists($webpPath) && filemtime($webpPath) >= filemtime($fullImagePath)) {
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

    private static function isConvertibleMimeType(string $mimeType): bool
    {
        return in_array($mimeType, ['image/jpeg', 'image/png'], true);
    }

    /**
     * @param array<string, bool|int|float|string|null> $options
     * @return array{alt:string,pictureClass:string,imgClass:string,quality:int,convert:bool,width:mixed,height:mixed}
     */
    private static function normalizeRenderOptions(array $options): array
    {
        return [
            'alt' => (string) ($options['alt'] ?? ''),
            'pictureClass' => (string) ($options['pictureClass'] ?? ''),
            'imgClass' => (string) ($options['imgClass'] ?? $options['class'] ?? ''),
            'quality' => self::normalizeQuality($options['quality'] ?? 80),
            'convert' => ($options['convert'] ?? true) !== false,
            'width' => $options['width'] ?? null,
            'height' => $options['height'] ?? null,
        ];
    }

    private static function normalizeQuality(mixed $quality): int
    {
        if (!is_int($quality) && !is_float($quality) && !is_string($quality)) {
            return 80;
        }

        return max(0, min(100, (int) $quality));
    }

    private static function normalizeDimension(mixed $dimension): string
    {
        if (!is_int($dimension) && !is_float($dimension) && !is_string($dimension)) {
            return '';
        }

        $dimension = (int) $dimension;
        if ($dimension <= 0) {
            return '';
        }

        return (string) $dimension;
    }

    private static function getMimeType(string $imagePath, string $fullImagePath): ?string
    {
        $extension = strtolower((string) pathinfo($imagePath, PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            return 'image/svg+xml';
        }

        if ($extension === 'webp') {
            return 'image/webp';
        }

        $imageSize = @getimagesize($fullImagePath);
        if ($imageSize === false || !isset($imageSize['mime']) || !is_string($imageSize['mime'])) {
            return null;
        }

        return match ($imageSize['mime']) {
            'image/jpeg', 'image/png' => $imageSize['mime'],
            default => null,
        };
    }

    /**
     * @param array<string, bool|int|float|string|null> $attributes
     */
    private static function renderImageAttributes(array $attributes): string
    {
        foreach (['alt', 'class', 'convert', 'height', 'imgClass', 'pictureClass', 'quality', 'width'] as $reservedOption) {
            unset($attributes[$reservedOption]);
        }

        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $safeName = self::sanitizeAttributeName((string) $name);
            if ($safeName === '') {
                continue;
            }

            if ($value === true) {
                $html .= " {$safeName}";
                continue;
            }

            $safeValue = self::escapeHtmlAttribute((string) $value);
            $html .= " {$safeName}=\"{$safeValue}\"";
        }

        return $html;
    }

    private static function sanitizeAttributeName(string $name): string
    {
        if (preg_match('/^[a-zA-Z_:][a-zA-Z0-9:_.-]*$/', $name) !== 1) {
            return '';
        }

        $lowercaseName = strtolower($name);
        if (str_starts_with($lowercaseName, 'on')) {
            return '';
        }

        if (in_array($lowercaseName, ['alt', 'class', 'height', 'src', 'srcset', 'width'], true)) {
            return '';
        }

        return $name;
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
        $directory = trim((string) ($imagePathInfo['dirname'] ?? ''), '/');
        $basePath = self::normalizeBaseDirectory() . ($directory !== '' ? $directory . '/' : '');

        return $basePath . self::$webpDir . $imagePathInfo['filename'] . "_{$quality}.webp";
    }

    private static function getWebPRelativePath(string $imagePath, int $quality): string
    {
        $imagePathInfo = pathinfo($imagePath);
        $directory = trim((string) ($imagePathInfo['dirname'] ?? ''), '/');
        $basePath = '/' . ($directory !== '' ? $directory . '/' : '');

        return self::toPublicUrl($basePath . self::$webpDir . $imagePathInfo['filename'] . "_{$quality}.webp");
    }

    private static function resolveFullImagePath(string $imagePath): ?string
    {
        $fullPath = self::normalizeBaseDirectory() . ltrim($imagePath, '/');
        $resolved = realpath($fullPath);
        if ($resolved === false) {
            return null;
        }

        $baseDirectory = rtrim(realpath(self::normalizeBaseDirectory()) ?: self::normalizeBaseDirectory(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($resolved, $baseDirectory)) {
            return null;
        }

        return $resolved;
    }

    private static function normalizeImagePath(string $imagePath): ?string
    {
        if ($imagePath === '' || str_contains($imagePath, "\0")) {
            return null;
        }

        $path = str_replace('\\', '/', trim($imagePath));
        $segments = explode('/', ltrim($path, '/'));
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return null;
            }
            $normalized[] = $segment;
        }

        if ($normalized === []) {
            return null;
        }

        return '/' . implode('/', $normalized);
    }

    private static function normalizeBaseDirectory(): string
    {
        return rtrim(str_replace('\\', '/', self::$imageDir), '/') . '/';
    }

    private static function toPublicUrl(string $path): string
    {
        return PublicUrl::toPublicUrl($path);
    }

    private static function escapeHtmlAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
