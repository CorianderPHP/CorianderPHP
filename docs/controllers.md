# Controller Guide

Controllers handle HTTP requests and live under `src/Controllers` (or `src/ApiControllers` for API endpoints).

Register controller actions explicitly using the [routing guide](routing.md).
Automatic controller and view discovery requires `new Router(automaticRouting: true)`;
route-level authorization alone does not protect aliases in that mode.

## Creating a Controller

Generate a controller with the CLI:

```bash
php coriander make:controller Blog
# or create an API controller
php coriander make:controller Blog --api
```

The command creates `BlogController.php` with basic `index`, `show`, and `store` methods. Controllers render views using the `ViewRenderer` helper and should wrap risky operations in `try`/`catch` blocks to log errors gracefully.

Generated files declare the matching class (`Controllers\BlogController` or
`ApiControllers\BlogController`). For older generated files, rename a remaining
`ControllerTemplatePlaceholder` declaration to match the filename.

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

## Automatic Action Methods

Automatic web-controller actions enforce HTTP methods and return 405 with an
`Allow` header when the method is unsupported. Defaults are GET/HEAD for read
actions, POST for `store`, POST/PUT/PATCH for `update`, and POST/DELETE for
`delete` or `destroy`. Action names are case-insensitive. POST requests without
an explicit action still select `store` when it exists.

Declare custom methods with a PHP attribute:

```php
use CorianderCore\Core\Router\HttpMethods;

#[HttpMethods('POST')]
public function resetPassword(): void
{
    // CSRF middleware validates this mutating web request before dispatch.
}
```

Compatibility: actions that previously accepted arbitrary verbs must declare
them with `HttpMethods` or use explicit router callbacks. Annotate every custom
mutating action; GET and HEAD must never change application state. GET declarations
also allow HEAD. API controller verb naming remains unchanged.

## Best Practices

- Keep actions small and delegate business logic to separate classes.
- Name controllers in PascalCase; the CLI appends `Controller` automatically.
- Place API controllers under `src/ApiControllers` and return JSON responses.
- Validate input and use CSRF guards for state-changing requests (`POST`, `PUT`, `PATCH`, `DELETE`).
