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
}
