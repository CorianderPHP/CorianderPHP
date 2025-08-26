# Cache Command Guide

The `cache` command speeds up routing by precomputing controller file paths stored in `cache/controllers.php`.

## Usage

Build the controller cache:

```bash
php coriander cache controllers
```

Generate every available cache:

```bash
php coriander cache all
```

Clear existing caches:

```bash
php coriander cache clear
```

## Best Practices

- Regenerate the controller cache after adding or renaming controllers.
- Do not commit generated cache files to version control.
- Use `cache clear` before deploying to ensure caches rebuild on the target environment.

