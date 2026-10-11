<?php
return static fn($request) => \CorianderCore\Core\Http\Responses::json(['name' => $request->getParsedBody()['name'] ?? null]);
