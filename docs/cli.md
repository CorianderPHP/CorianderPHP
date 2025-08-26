# CLI Guide

CorianderPHP provides a command-line interface accessible via `php coriander` for scaffolding and maintenance tasks.

## Usage

Invoke commands from the project root:

```bash
php coriander <command> [arguments]
```

Examples:

- `php coriander make:view Home`
- `php coriander cache controllers`
- `php coriander nodejs run build-ts`

## Error Handling

- Commands print diagnostic messages prefixed with `[Error]` or `[Warning]` when something goes wrong.
- Most commands exit silently on success and non-zero on failure, allowing usage in scripts.

## Best Practices

- Run the CLI from the project root so generated files resolve to correct paths.
- Inspect output carefully; many commands provide hints for missing dependencies or misconfigurations.
- Rebuild caches (`php coriander cache controllers`) after adding controllers or clearing the `cache/` directory.

