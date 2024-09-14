<?php

namespace Controllers;

/**
 * Class {{controllerName}}
 *
 * This is the controller for handling logic related to {{controllerName}}.
 * You can add your methods here to manage routes and render views.
 */
class {{controllerName}}
{
    /**
     * Display the default page for this controller.
     *
     * This method is intended to handle the default route for the controller.
     * You can modify it to fetch data, interact with models, or simply render a view.
     */
    public function index()
    {
        // Render the default view for this controller
        require PROJECT_ROOT . '/public/public_views/{{kebabControllerName}}/index.php';
    }

    /**
     * Show a specific item by ID or slug (optional).
     *
     * @param mixed $id The ID or slug of the item to display.
     * You can modify this method to fetch data based on the passed ID or slug.
     */
    public function show($id)
    {
        // Example: Fetch an item from a database (add your logic here)
        // $item = YourModel::find($id);

        // Render the view for showing a specific item
        require PROJECT_ROOT . '/public/public_views/{{kebabControllerName}}/show.php';
    }

    /**
     * Handle a form submission or other POST request (optional).
     *
     * This method can be used to process form submissions or other actions.
     */
    public function store()
    {
        // Handle form submission data
        // Example: $formData = $_POST;

        // Redirect or render a success page
        header('Location: /{{kebabControllerName}}/success');
    }
}
