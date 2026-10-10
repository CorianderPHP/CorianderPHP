<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/config/config.php';
require_once PROJECT_ROOT . '/CorianderCore/autoload.php';
use CorianderCore\Core\Bootstrap\SessionBootstrap;
use CorianderCore\Core\Http\{ErrorResponse, RequestFactory, ResponseEmitter};
use CorianderCore\Core\Router\Router;
try {
    SessionBootstrap::configure(false);
    $response = (new Router(__DIR__ . '/Routes'))->handle(RequestFactory::fromGlobals(maxBodyBytes: 1024));
} catch (Throwable $exception) {
    $response = ErrorResponse::fromException($exception);
}
ResponseEmitter::emit($response, $_SERVER['REQUEST_METHOD']);
