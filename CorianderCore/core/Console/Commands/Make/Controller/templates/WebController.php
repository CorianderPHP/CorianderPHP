<?php
declare(strict_types=1);

/**
 * Template for creating standard web controllers.
 *
 * Workflow:
 * - Instantiates a {@see ViewRenderer} for view rendering.
 * - Provides example actions (index, show, store) that demonstrate passing
 *   data to views and handling route logic.
 */
namespace Controllers;

use CorianderCore\Core\Router\ViewRenderer;
use CorianderCore\Core\Security\Csrf;

/**
 * Class {{controllerName}}
 *
 * This class handles the logic and routes related to the {{controllerName}}.
 * Methods in this class manage route actions, view rendering, and interactions with models.
 */
class {{controllerName}}
{
    /**
     * Handles view rendering.
     *
     * @var ViewRenderer
     */
    protected ViewRenderer $view;

    /**
     * Initialize the controller with a view renderer instance.
     */
    public function __construct()
    {
        $this->view = new ViewRenderer();
    }

    /**
     * Display the default page for this controller.
     *
     * @return void
     */
    public function index(): void
    {
        $data = [
            // Example data
        ];

        // Use the ViewRenderer to render the view
        $this->view->render('{{kebabControllerName}}', $data);
    }

    /**
     * Show a specific item by ID or slug.
     *
     * This method handles displaying a specific resource based on an ID or slug passed to it.
     * Modify this method to interact with your models to fetch data.
     *
     * @param mixed $id The ID or slug of the resource to display.
     * @return void
     */
    public function show(mixed $id): void
    {
        // Example: Fetch an item from the database
        $data = [
            // Example: 'item' => YourModel::find($id)
        ];

        // Use the ViewRenderer to render the specific view
        $this->view->render('{{kebabControllerName}}/show', $data);
    }

    /**
     * Handle a form submission or other POST request.
     *
     * This method processes data submitted via POST requests, such as form submissions.
     * It validates and handles the data, rendering a success message or redirection to another view.
     *
     * @return void
     */
    public function store(): void
    {
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        // Example: Process form submission data
        // $formData = $_POST;
        // Validation and processing logic here

        // Example: Success message
        $data = [
            'message' => 'Data submitted successfully.'
        ];

        // Use the ViewRenderer to render the success view
        $this->view->render('{{kebabControllerName}}/success', $data);
    }
}

