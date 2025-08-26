# Sitemap Guide

CorianderPHP can generate `sitemap.xml` to improve SEO.

## Generating the Sitemap Script

Create the `public/sitemap.php` helper via CLI:

```bash
php coriander make:sitemap
```

The generated script rebuilds `sitemap.xml` when missing or outdated and serves it on the `/sitemap.xml` route.

## Adding Pages

Static views include a `metadata.php` file where you can control sitemap settings:

```php
<?php
$addViewInSitemap = true;   // include this page
$sitemapPriority  = 0.6;    // priority between 0.0 and 1.0
```

Dynamic URLs can be added programmatically:

```php
$handler = new \CorianderCore\Core\Sitemap\SitemapHandler();
$handler->addDynamicPage(PROJECT_URL . '/blog/post-1', 0.8, '2024-05-01');
$handler->generateSitemap();
```

## Best Practices

- Rebuild the sitemap after adding or removing views or dynamic content.
- Serve the sitemap at the site root to help search engines discover it quickly.
- Use descriptive priorities and last-modified dates to hint search engines about page importance.

