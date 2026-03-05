<?php

use CorianderCore\Core\Bootstrap\TimezoneBootstrap;
use CorianderCore\Core\Container\Container;
use CorianderCore\Core\Database\DatabaseHandler;
use CorianderCore\Core\Logging\Logger;
use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Security\ApiRequestLimitsMiddleware;
use CorianderCore\Core\Security\CsrfMiddleware;
use CorianderCore\Core\Security\SecurityHeadersMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once '../config/config.php';

if (file_exists(PROJECT_ROOT . '/CorianderCore/autoload.php')) {
    require_once PROJECT_ROOT . '/CorianderCore/autoload.php';
}

if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require_once PROJECT_ROOT . '/vendor/autoload.php';
}

$appTimezone = getenv('APP_TIMEZONE');
TimezoneBootstrap::applyFromEnvironment(is_string($appTimezone) ? $appTimezone : null);

try {
    $container = new Container();
    $container->set(Logger::class, fn() => new Logger());
    $container->set(DatabaseHandler::class, fn(Container $c) => new DatabaseHandler($c->get(Logger::class)));
    $container->set(Router::class, fn() => new Router());

    $router = $container->get(Router::class);
    $router->addMiddleware(new SecurityHeadersMiddleware());
    $router->addMiddleware(new ApiRequestLimitsMiddleware());
    $router->addMiddleware(new CsrfMiddleware());

    $notFound = function () {
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
    $router->setNotFound($notFound);

    $routesFile = __DIR__ . '/routes.php';
    if (file_exists($routesFile)) {
        require $routesFile;
    }

    $serverParams = $_SERVER;
    $protocol = $serverParams['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    $version = str_contains($protocol, '/') ? substr($protocol, strpos($protocol, '/') + 1) : '1.1';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $rawBody = file_get_contents('php://input');

    $request = new ServerRequest(
        $serverParams['REQUEST_METHOD'] ?? 'GET',
        $serverParams['REQUEST_URI'] ?? '/',
        $headers,
        $rawBody,
        $version,
        $serverParams
    );

    $contentType = strtolower((string) ($headers['Content-Type'] ?? $headers['content-type'] ?? ''));
    $method = strtoupper($serverParams['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody ?: '', true);
            if (is_array($decoded)) {
                $request = $request->withParsedBody($decoded);
            }
        } elseif (!empty($_POST)) {
            $request = $request->withParsedBody($_POST);
        }
    }

    $response = $router->dispatch($request);

    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header($name . ': ' . $value, false);
        }
    }
    echo $response->getBody();
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
