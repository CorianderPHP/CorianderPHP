<?php
// Set the language attribute for the <html> tag on the {{viewName}} page.
$lang = isset($lang) ? $lang : 'en';

// SEO metadata: Title and meta tags for the {{viewName}} page.
$metadata = isset($metadata) ? $metadata : '
<title>{{viewName}} page</title>
<meta name="description" content="This is the {{viewName}} page description.">
';

// Include this page in the sitemap for SEO purposes.
$addViewInSitemap = isset($addViewInSitemap) ? $addViewInSitemap : true;

// Set sitemap priority for this page (0.0 - lowest, 1.0 - highest).
$sitemapPriority = isset($sitemapPriority) ? $sitemapPriority : 0.8;
