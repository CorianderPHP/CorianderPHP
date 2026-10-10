<?php
return [new class implements \Psr\Http\Server\MiddlewareInterface {
    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface {
        \CorianderCore\Core\Bootstrap\SessionBootstrap::start();
        return isset($_SESSION['visits']) ? $handler->handle($request) : \CorianderCore\Core\Http\Responses::html('Forbidden', 403);
    }
}];
