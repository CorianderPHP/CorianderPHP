<?php
return static fn($r) => \CorianderCore\Core\Http\Responses::json([
    'team' => $r->getAttribute('team'), 'id' => $r->getAttribute('id'), 'query' => $r->getQueryParams(),
]);
