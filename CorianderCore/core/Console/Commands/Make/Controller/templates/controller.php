<?php

namespace Controllers;

use CorianderCore\Router\Router;

/**
 * Class {{controllerName}}
 *
 * This class handles the logic and routes related to the {{controllerName}}.
 * Methods in this class manage route actions, view rendering, and interactions with models.
 */
class {{controllerName}}
{
    protected $router;

    public function __construct()
    {
        // Get the static instance of the router
        $this->router = Router::getInstance();
    }

    /**
     * Display the default page for this controller.
     */
    public function index()
    {
        $data = [
            // Example data
        ];

        // Use the router instance to render the view
        $this->router::renderView('{{kebabControllerName}}', $data);
    }

    /**
     * Show a specific item by ID or slug.
     *
     * This method handles displaying a specific resource based on an ID or slug passed to it.
     * Modify this method to interact with your models to fetch data.
     *
     * @param mixed $id The ID or slug of the resource to display.
     */
    public function show($id)
    {
        // Example: Fetch an item from the database
        $data = [
            // Example: 'item' => YourModel::find($id)
        ];

        // Use the router instance to render the specific view
        $this->router::renderView('{{kebabControllerName}}/show', $data);
    }

    /**
     * Handle a form submission or other POST request.
     *
     * This method processes data submitted via POST requests, such as form submissions.
     * It validates and handles the data, rendering a success message or redirection to another view.
     */
    public function store()
    {
        // Example: Process form submission data
        // $formData = $_POST;
        // Validation and processing logic here

        // Example: Success message
        $data = [
            'message' => 'Data submitted successfully.'
        ];

        // Use the router instance to render the success view
        $this->router::renderView('{{kebabControllerName}}/success', $data);
    }
}
