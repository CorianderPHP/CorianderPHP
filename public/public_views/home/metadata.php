<?php
// Set the language attribute for the <html> tag on the home page.
$lang = !isset($lang) ?: 'en';

// SEO metadata: Title and meta tags for the home page.
$metadata = !isset($metadata) ?? '
<title>home page</title>
<meta name="description" content="This is the home page description.">
';

// Include this page in the sitemap for SEO purposes.
$addViewInSitemap = !isset($addViewInSitemap) ?? true;

// Set sitemap priority for this page (0.0 - lowest, 1.0 - highest).
$sitemapPriority = !isset($sitemapPriority) ?? 0.8;
