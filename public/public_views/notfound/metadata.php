<?php
// Set the language attribute for the <html> tag on the notfound page.
$lang = isset($lang) ? $lang : 'en';

// SEO metadata: Title and meta tags for the notfound page.
$metadata = isset($metadata) ? $metadata : '
<title>notfound page</title>
<meta name="description" content="This is the notfound page description.">
';

// Include this page in the sitemap for SEO purposes.
$addViewInSitemap = false;

// Set sitemap priority for this page (0.0 - lowest, 1.0 - highest).
$sitemapPriority = 0.0;
