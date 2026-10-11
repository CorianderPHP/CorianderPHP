<?php
return static fn($r) => (new \CorianderCore\Core\Router\ViewRenderer(dirname(__DIR__) . '/Views'))->response('home');
