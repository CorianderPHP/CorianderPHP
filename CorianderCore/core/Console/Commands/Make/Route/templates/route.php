<?php
declare(strict_types=1);
use CorianderCore\Core\Http\Responses;
use Psr\Http\Message\ServerRequestInterface;
return static fn(ServerRequestInterface $request) => Responses::html('Hello');
