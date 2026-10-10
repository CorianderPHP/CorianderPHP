<?php
declare(strict_types=1);
use CorianderCore\Core\Sitemap\SitemapHandler;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
return static function (ServerRequestInterface $request): Response {
    $sitemap = new SitemapHandler();
    $sitemap->addDynamicPage(rtrim(PROJECT_URL, '/') . '/', 0.8);
    // Add other public URLs explicitly; never list protected routes automatically.
    return new Response(200, ['Content-Type' => 'application/xml; charset=utf-8'], $sitemap->toXml());
};
