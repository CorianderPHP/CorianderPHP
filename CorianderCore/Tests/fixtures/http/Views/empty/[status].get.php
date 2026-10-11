<?php
return static fn($request) => new \Nyholm\Psr7\Response((int) $request->getAttribute('status'),
    ['Content-Length' => '999', 'Transfer-Encoding' => 'chunked', 'X-Fixture' => 'preserved'], 'must not be emitted');
