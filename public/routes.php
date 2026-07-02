<?php

use Nyholm\Psr7\ServerRequest;

/** @var \CorianderCore\Core\Router\Router $router */
/** @var callable $notFound */

// Register custom routes here. This file is included by index.php and
// has access to the `$router` and `$notFound` variables.

// Example dynamic route: /user/42 -> "User ID: 42"
// $router->get('user/{id}', function (ServerRequest $request) {
//     echo 'User ID: ' . $request->getAttribute('id');
// });

// Route for handling sitemap.xml requests
$router->get('sitemap.xml', function (ServerRequest $request) use ($notFound) {
    $sitemapPath = PROJECT_ROOT . '/public/sitemap.php';
    if (!file_exists($sitemapPath)) {
        return $notFound();
    }
    require_once $sitemapPath;
});
