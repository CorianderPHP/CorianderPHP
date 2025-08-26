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

## Best Practices

- Keep view logic minimal; handle business logic in controllers or services.
- Escape dynamic output with `htmlspecialchars` to avoid XSS issues.
- Store assets under `public/assets` and reference them with absolute paths.

