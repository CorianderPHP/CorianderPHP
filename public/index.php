<?php
ob_start();
date_default_timezone_set("Europe/Paris");
session_start();

require_once '../settings/settings.php';

// Check if CorianderCore's autoloader exists and include it
if (file_exists(PROJECT_ROOT . '/CorianderCore/autoload.php')) {
    require_once PROJECT_ROOT . '/CorianderCore/autoload.php';
}

// Check if Composer's autoloader exists and include it
if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require_once PROJECT_ROOT . '/vendor/autoload.php';
}

use CorianderCore\Router\Router;

// Initialize the router
$router = new Router();

// Custom 404 handler
$router->setNotFound(function () {
    $notFoundView = "notfound";
    
    $metaDataFile = PROJECT_ROOT . '/public/public_views/' . $notFoundView . '/metadata.php';

    // If a metadata.php file exists, include it to override defaults
    if (file_exists($metaDataFile)) {
        include $metaDataFile;
    }

    require_once  PROJECT_ROOT . '/public/public_views/header.php';
    require_once PROJECT_ROOT . '/public/public_views/' . $notFoundView . '/index.php';
    require_once  PROJECT_ROOT . '/public/public_views/footer.php';
});

// Dispatch the request to the correct view or controller
$router->dispatch();

ob_end_flush();