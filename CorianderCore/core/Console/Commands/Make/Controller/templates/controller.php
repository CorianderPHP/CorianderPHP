<?php

namespace Controllers;

/**
 * Class {{controllerName}}
 *
 * This class handles the logic and routes related to the {{controllerName}}.
 * Methods in this class manage route actions, view rendering, and interactions with models.
 */
class {{controllerName}}
{
    /**
     * Display the default page for this controller.
     *
     * This method handles the default route for the controller, 
     * typically used to render the main page of a resource or section.
     * You can fetch data, interact with models, or simply render a view.
     */
    public function index()
    {
        // Include the metadata specific to this view.
        require PROJECT_ROOT . '/public/public_views/{{kebabControllerName}}/metadata.php';

        // Render the common header.
        require PROJECT_ROOT . '/public/public_views/header.php';

        // Render the default index view for this controller.
        require PROJECT_ROOT . '/public/public_views/{{kebabControllerName}}/index.php';

        // Render the common footer.
        require PROJECT_ROOT . '/public/public_views/footer.php';
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
        // Example: Fetch an item from the database (modify as needed)
        // $item = YourModel::find($id);

        // Include the metadata specific to this view.
        require PROJECT_ROOT . '/public/public_views/{{kebabControllerName}}/metadata.php';

        // Render the common header.
        require PROJECT_ROOT . '/public/public_views/header.php';

        // Render the specific item view.
        require PROJECT_ROOT . '/public/public_views/{{kebabControllerName}}/show.php';

        // Render the common footer.
        require PROJECT_ROOT . '/public/public_views/footer.php';
    }

    /**
     * Handle a form submission or other POST request.
     *
     * This method processes data submitted via POST requests, such as form submissions.
     * It validates and handles the data, rendering a success message or redirection to another view.
     */
    public function store()
    {
        // Process form submission data (e.g., $formData = $_POST).
        // Add your form validation and handling logic here.

        // Include the metadata specific to this view.
        require PROJECT_ROOT . '/public/public_views/{{kebabControllerName}}/metadata.php';

        // Render the common header.
        require PROJECT_ROOT . '/public/public_views/header.php';

        // Output a success message or perform further actions.
        echo "Data submitted successfully.";

        // Optionally, redirect to a success page.
        // To implement, create 'success.php' in '/public/public_views/{{kebabControllerName}}/'
        // require PROJECT_ROOT . '/public/public_views/{{kebabControllerName}}/success.php';

        // Render the common footer.
        require PROJECT_ROOT . '/public/public_views/footer.php';
    }
}
