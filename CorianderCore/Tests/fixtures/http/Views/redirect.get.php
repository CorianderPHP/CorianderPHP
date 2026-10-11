<?php
return static fn($request) => \CorianderCore\Core\Http\Responses::redirect('/')->withHeader('X-Redirect', 'fixture');
