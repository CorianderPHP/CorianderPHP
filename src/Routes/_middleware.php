<?php
declare(strict_types=1);
use App\Middleware\NotFoundMiddleware;
use CorianderCore\Core\Security\{ApiRequestLimitsMiddleware, CsrfMiddleware, SecurityHeadersMiddleware};
return [
    new SecurityHeadersMiddleware(),
    new ApiRequestLimitsMiddleware(API_MAX_BODY_BYTES, API_TIMEOUT_SECONDS, apiPrefixes: []),
    new CsrfMiddleware(),
    new NotFoundMiddleware(),
];
