<?php
date_default_timezone_set("Europe/Paris");
session_start();

require_once '../config/config.php';

// Check if CorianderCore's autoloader exists and include it
if (file_exists(PROJECT_ROOT . '/CorianderCore/autoload.php')) {
    require_once PROJECT_ROOT . '/CorianderCore/autoload.php';
}

// Check if Composer's autoloader exists and include it
if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require_once PROJECT_ROOT . '/vendor/autoload.php';
}

use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Security\CsrfMiddleware;
use Nyholm\Psr7\ServerRequest;

// Initialize the router
$router = Router::getInstance();
$router->addMiddleware(new CsrfMiddleware());

// Custom 404 handler
$notFound = function () {
    $notFoundView = "notfound";

    $metaDataFile = PROJECT_ROOT . '/public/public_views/' . $notFoundView . '/metadata.php';

    // If a metadata.php file exists, include it to override defaults
    if (file_exists($metaDataFile)) {
        include $metaDataFile;
    }

    require_once PROJECT_ROOT . '/public/public_views/header.php';
    require_once PROJECT_ROOT . '/public/public_views/' . $notFoundView . '/index.php';
    require_once PROJECT_ROOT . '/public/public_views/footer.php';
};
$router->setNotFound($notFound);

// Load project-specific routes if available
$routesFile = __DIR__ . '/routes.php';
if (file_exists($routesFile)) {
    require $routesFile;
}

// Dispatch the request to the correct view or controller
$request = new ServerRequest(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/',
    function_exists('getallheaders') ? getallheaders() : [],
    file_get_contents('php://input'),
    $_SERVER
);
$response = $router->dispatch($request);

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}
echo $response->getBody();
