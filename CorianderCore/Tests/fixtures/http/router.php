<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/config/config.php';
require_once PROJECT_ROOT . '/CorianderCore/autoload.php';
use CorianderCore\Core\Bootstrap\SessionBootstrap;
use CorianderCore\Core\Http\{BadRequestException, PayloadTooLargeException, RequestFactory, ResponseEmitter, Responses};
use CorianderCore\Core\Router\Router;
try {
    SessionBootstrap::configure(false);
    SessionBootstrap::startForRequest($_SERVER, []);
    $response = (new Router(__DIR__ . '/Routes'))->handle(RequestFactory::fromGlobals(maxBodyBytes: 1024));
} catch (PayloadTooLargeException) {
    $response = Responses::html('Payload Too Large', 413);
} catch (BadRequestException) {
    $response = Responses::html('Bad Request', 400);
} catch (Throwable) {
    $response = Responses::html('Internal Server Error', 500);
}
ResponseEmitter::emit($response, $_SERVER['REQUEST_METHOD']);
