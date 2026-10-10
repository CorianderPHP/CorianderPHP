<?php

use Nyholm\Psr7\ServerRequest;
use CorianderCore\Core\Router\ViewRenderer;

/** @var \CorianderCore\Core\Router\Router $router */
/** @var callable $notFound */

// Register custom routes here. This file is included by index.php and
// has access to the `$router` and `$notFound` variables.
//
// Small projects can keep all custom routes in this file. Larger projects can
// split routes into src/Routes/*.php and include them here:
// $adminRoutes = PROJECT_ROOT . '/src/Routes/admin.php';
// if (is_file($adminRoutes)) {
//     (require $adminRoutes)($router);
// }

// Example dynamic route: /user/42 -> "User ID: 42"
// $router->get('user/{id}', function (ServerRequest $request) {
//     echo 'User ID: ' . $request->getAttribute('id');
// });

// Register the homepage and its /home alias explicitly.
$homepage = static fn() => (new ViewRenderer())->render('home');
$router->get('/', $homepage);
$router->get('home', $homepage);

// Route for handling sitemap.xml requests
$router->get('sitemap.xml', function (ServerRequest $request) use ($notFound) {
    $sitemapPath = PROJECT_ROOT . '/public/sitemap.php';
    if (!file_exists($sitemapPath)) {
        return $notFound();
    }
    require_once $sitemapPath;
});
