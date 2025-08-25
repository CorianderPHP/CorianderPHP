<?php

/**
 * Template for generating API controllers.
 *
 * Workflow:
 * - Exposes basic `get` and `post` handlers returning JSON responses.
 * - Can be extended with additional actions for sub-routes.
 */
namespace ApiControllers;

use CorianderCore\Core\Security\Csrf;

/**
 * Class {{controllerName}}
 *
 * Handles API requests for the {{controllerName}} resource.
 */
class {{controllerName}}
{
    /**
     * Handles GET requests to /api/{{kebabControllerName}}.
     *
     * @return void
     */
    public function get(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'OK', 'data' => []]);
    }

    /**
     * Handles POST requests to /api/{{kebabControllerName}}.
     *
     * @return void
     */
    public function post(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!Csrf::validate($input['csrf_token'] ?? null)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid CSRF token']);
            return;
        }
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Data received', 'data' => $input]);
    }

    // -------------------------------------------------------------------
    // Example: Handling subpath actions
    // -------------------------------------------------------------------
    //
    // The following optional methods demonstrate how to handle
    // sub-routes such as:
    //
    // GET  /api/{{kebabControllerName}}/stats      → get_stats()
    // GET  /api/{{kebabControllerName}}/stats/123  → get_stats(123)
    // POST /api/{{kebabControllerName}}/summary    → post_summary()
    //
    // Uncomment and customize as needed.
    //
    // -------------------------------------------------------------------

    /*
    public function get_stats(int $id = null): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'OK',
            'action' => 'get_stats',
            'id'     => $id
        ]);
    }

    public function post_summary(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'OK',
            'action' => 'post_summary',
            'data'   => $input
        ]);
    }
    */
}
