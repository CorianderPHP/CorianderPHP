# Sitemap

`src/Routes/sitemap.xml.get.php` returns an XML response. Add public URLs explicitly through `SitemapHandler::addDynamicPage($url, $priority, $lastmod)` and render with `toXml()`. The starter includes `/` only. Discovery never implies sitemap inclusion, so protected admin routes stay out of the sitemap.

`php coriander make:sitemap` creates a handler if one does not exist. Edit it to add your public pages. `generateSitemap($outputDirectory)` remains available for applications that need a saved XML file. Metadata files and view scanning were removed in 0.3.0.
