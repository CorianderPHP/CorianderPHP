<?php
return [
    new \CorianderCore\Core\Security\SecurityHeadersMiddleware(),
    new \CorianderCore\Core\Security\ApiRequestLimitsMiddleware(1024, apiPrefixes: []),
    new \CorianderCore\Core\Security\CsrfMiddleware(),
];
