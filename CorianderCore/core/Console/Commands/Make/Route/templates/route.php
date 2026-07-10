<?php
declare(strict_types=1);

use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

return static function (Router $router): void {
    $router->get('{{routeName}}', static function (ServerRequest $request): Response {
        return new Response(200, [], '{{routeName}} route');
    });
};
