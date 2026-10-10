<?php
declare(strict_types=1);
use CorianderCore\Core\Http\Responses;
use Psr\Http\Message\ServerRequestInterface;
return static fn(ServerRequestInterface $request) => Responses::view('home', [
    'title' => 'Welcome to CorianderPHP',
    'description' => 'A lightweight PHP framework with file routes and simple developer UX.',
]);
