<?php
declare(strict_types=1);

/**
 * Template for generating API controllers.
 *
 * Workflow:
 * - Exposes basic `get` and `post` handlers returning JSON responses.
 * - Can be extended with additional actions for sub-routes.
 */
namespace ApiControllers;

use CorianderCore\Core\Security\Csrf;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Class {{controllerName}}
 *
 * Handles API requests for the {{controllerName}} resource.
 */
class ControllerTemplatePlaceholder
{
    /**
     * Handles GET requests to /api/{{kebabControllerName}}.
     */
    public function get(): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(['status' => 'OK', 'data' => []]));
    }

    /**
     * Handles POST requests to /api/{{kebabControllerName}}.
     */
    public function post(): ResponseInterface
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? null;
        if (!is_string($token) || !Csrf::validate($token)) {
            return new Response(403, ['Content-Type' => 'application/json; charset=utf-8'],
                json_encode(['error' => 'Invalid CSRF token']));
        }

        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(['message' => 'Data received', 'data' => $input]));
    }

    // -------------------------------------------------------------------
    // Example: Handling subpath actions
    // -------------------------------------------------------------------
    //
    // The following optional methods demonstrate how to handle sub-routes:
    // GET  /api/{{kebabControllerName}}/stats      -> get_stats()
    // GET  /api/{{kebabControllerName}}/stats/123  -> get_stats(123)
    // POST /api/{{kebabControllerName}}/summary    -> post_summary()
    //
    // Uncomment and customize as needed.
    // -------------------------------------------------------------------

    /*
    public function get_stats(?int $id = null): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
            'status' => 'OK',
            'action' => 'get_stats',
            'id' => $id,
        ]));
    }

    public function post_summary(): ResponseInterface
    {
        $input = json_decode(file_get_contents('php://input'), true);
        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
            'status' => 'OK',
            'action' => 'post_summary',
            'data' => $input,
        ]));
    }
    */
}
