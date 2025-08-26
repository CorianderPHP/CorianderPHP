# Controller Guide

Controllers handle HTTP requests and live under `src/Controllers` (or `src/ApiControllers` for API endpoints).

## Creating a Controller

Generate a controller with the CLI:

```bash
php coriander make:controller Blog
# or create an API controller
php coriander make:controller Blog --api
```

The command creates `BlogController.php` with basic `index`, `show`, and `store` methods. Controllers render views using the `ViewRenderer` helper and should wrap risky operations in `try`/`catch` blocks to log errors gracefully.

```php
public function show($id): void
{
    try {
        $item = ArticleRepository::find($id);
        $this->view->render('blog/show', ['item' => $item]);
    } catch (\Throwable $e) {
        // log error and return proper response
    }
}
```

## Best Practices

- Keep actions small and delegate business logic to separate classes.
- Name controllers in PascalCase; the CLI appends `Controller` automatically.
- Place API controllers under `src/ApiControllers` and return JSON responses.
- Validate input and use CSRF guards for state-changing requests.

