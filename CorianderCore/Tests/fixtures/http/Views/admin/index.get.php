<?php
return static fn($r) => (new \CorianderCore\Core\Router\ViewRenderer(dirname(__DIR__, 2) . '/Views'))->response('admin/home');
