# View Guide

CorianderPHP views live in `public/public_views`. Each view contains an `index.php` template and a `metadata.php` file for sitemap settings.

## Creating a View

Generate a view with the CLI:

```bash
php coriander make:view Home
```

The command creates `public/public_views/home/index.php` and `metadata.php`. Update `metadata.php` to control sitemap inclusion:

```php
<?php
$addViewInSitemap = true;      // include page
$sitemapPriority  = 0.8;       // 0.0 - 1.0
```

## Rendering Images as WebP

Use the built-in `ImageHandler` to convert images to WebP on the fly inside a view:

```php
<?= \CorianderCore\Core\Image\ImageHandler::render('/public/assets/img/logo.png', 'Site logo'); ?>
```

The helper converts the image if needed and outputs a `<picture>` tag with WebP and fallback sources.

## Output Safety

Variables passed to view templates are automatically escaped for HTML output to mitigate XSS attacks.

## Path Safety

View resolution only accepts normalized relative paths under `public/public_views`.

- Dot-segments such as `.` or `..` are rejected.
- Absolute paths are rejected.
- Null-byte path fragments are rejected.

This prevents path traversal and accidental inclusion of files outside the view root.

## Best Practices

- Keep view logic minimal; handle business logic in controllers or services.
- Avoid double escaping; variables provided to views are sanitized by the framework.
- Store assets under `public/assets` and reference them with absolute paths.
