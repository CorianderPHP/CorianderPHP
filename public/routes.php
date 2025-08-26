<?php

// Register custom routes here. This file is included by index.php and
// has access to the `$router` and `$notFound` variables.

// Example dynamic route: /user/42 -> "User ID: 42"
// $router->add('GET', 'user/{id}', function (\Nyholm\Psr7\ServerRequest $request) {
//     echo 'User ID: ' . $request->getAttribute('id');
// });

// Route for handling sitemap.xml requests
$router->add('GET', 'sitemap.xml', function (\Nyholm\Psr7\ServerRequest $request) use ($notFound) {
    $sitemapPath = PROJECT_ROOT . '/public/sitemap.php';
    if (!file_exists($sitemapPath)) {
        $notFound();
        return;
    }
    require_once $sitemapPath;
});

