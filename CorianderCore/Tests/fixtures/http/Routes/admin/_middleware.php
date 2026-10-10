<?php
return [new class implements \Psr\Http\Server\MiddlewareInterface {
    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
    {
        return $request->getHeaderLine('Authorization') === 'Bearer fixture'
            ? $handler->handle($request)
            : new \Nyholm\Psr7\Response(403, [], 'Forbidden');
    }
}];
