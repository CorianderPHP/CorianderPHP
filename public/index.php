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

use CorianderCore\Core\Container\Container;
use CorianderCore\Core\Database\DatabaseHandler;
use CorianderCore\Core\Logging\Logger;
use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Security\CsrfMiddleware;
use Nyholm\Psr7\ServerRequest;

// Register core services
$container = new Container();
$container->set(Logger::class, fn() => new Logger());
$container->set(DatabaseHandler::class, fn(Container $c) => new DatabaseHandler($c->get(Logger::class)));
$container->set(Router::class, fn() => new Router());

// Initialize the router
$router = $container->get(Router::class);
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
$serverParams = $_SERVER;
$protocol = $serverParams['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
$version = str_contains($protocol, '/') ? substr($protocol, strpos($protocol, '/') + 1) : '1.1';

$request = new ServerRequest(
    $serverParams['REQUEST_METHOD'] ?? 'GET',
    $serverParams['REQUEST_URI'] ?? '/',
    function_exists('getallheaders') ? getallheaders() : [],
    file_get_contents('php://input'),
    $version,
    $serverParams
);

$response = $router->dispatch($request);

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}
echo $response->getBody();
