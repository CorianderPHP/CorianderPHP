<?php

namespace ApiControllers;

/**
 * Class {{controllerName}}
 *
 * Handles API requests for the {{controllerName}} resource.
 */
class {{controllerName}}
{
    /**
     * Handles GET requests to /api/{{kebabControllerName}}.
     */
    public function get(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'OK', 'data' => []]);
    }

    /**
     * Handles POST requests to /api/{{kebabControllerName}}.
     */
    public function post(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
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