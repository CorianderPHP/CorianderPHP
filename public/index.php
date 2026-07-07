<?php

use CorianderCore\Core\Bootstrap\SessionBootstrap;
use CorianderCore\Core\Bootstrap\TimezoneBootstrap;
use CorianderCore\Core\Container\Container;
use CorianderCore\Core\Database\DatabaseHandler;
use CorianderCore\Core\Http\RequestFactory;
use CorianderCore\Core\Http\ResponseEmitter;
use CorianderCore\Core\Http\TrustedProxy;
use CorianderCore\Core\Logging\Logger;
use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Security\ApiRequestLimitsMiddleware;
use CorianderCore\Core\Security\CsrfMiddleware;
use CorianderCore\Core\Security\SecurityHeadersMiddleware;
use Nyholm\Psr7\Response;


function corianderCreateNotFoundHandler(): callable
{
    return static function (): Response {
        $notFoundView = 'notfound';

        $metaDataFile = PROJECT_ROOT . '/public/public_views/' . $notFoundView . '/metadata.php';

        if (file_exists($metaDataFile)) {
            include $metaDataFile;
        }

        ob_start();
        require_once PROJECT_ROOT . '/public/public_views/header.php';
        require_once PROJECT_ROOT . '/public/public_views/' . $notFoundView . '/index.php';
        require_once PROJECT_ROOT . '/public/public_views/footer.php';

        return new Response(404, [], (string) ob_get_clean());
    };
}
require_once '../config/config.php';

if (file_exists(PROJECT_ROOT . '/CorianderCore/autoload.php')) {
    require_once PROJECT_ROOT . '/CorianderCore/autoload.php';
}

if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require_once PROJECT_ROOT . '/vendor/autoload.php';
}

$secure = TrustedProxy::isSecureRequest($_SERVER);
SessionBootstrap::configure($secure);
SessionBootstrap::startForRequest($_SERVER);

$appTimezone = getenv('APP_TIMEZONE');
TimezoneBootstrap::applyFromEnvironment(is_string($appTimezone) ? $appTimezone : null);

try {
    $container = new Container();
    $container->set(Logger::class, fn() => new Logger());
    $container->set(DatabaseHandler::class, fn(Container $c) => new DatabaseHandler($c->get(Logger::class)));
    $container->set(Router::class, fn() => new Router());

    $router = $container->get(Router::class);
    $router->addMiddleware(new SecurityHeadersMiddleware());
    $router->addMiddleware(new ApiRequestLimitsMiddleware(
        defined('API_MAX_BODY_BYTES') ? (int) API_MAX_BODY_BYTES : null,
        defined('API_TIMEOUT_SECONDS') ? (int) API_TIMEOUT_SECONDS : null
    ));
    $router->addMiddleware(new CsrfMiddleware());

    $notFound = corianderCreateNotFoundHandler();
    $router->setNotFound($notFound);

    $routesFile = __DIR__ . '/routes.php';
    if (file_exists($routesFile)) {
        require $routesFile;
    }

    ResponseEmitter::emit($router->dispatch(RequestFactory::fromGlobals()));
} catch (Throwable $exception) {
    try {
        (new Logger())->error('Unhandled application exception.', ['exception' => $exception]);
    } catch (Throwable) {
        // Avoid cascading failures while handling an already-failed request.
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'Internal Server Error';
}



