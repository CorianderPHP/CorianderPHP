# Database Module Guide

The database layer centralizes connection handling through `DatabaseHandler` instances registered in a service container and exposes helper methods via `SQLManager`.

## Creating Configuration via CLI

Generate a database configuration interactively:

```bash
php coriander make:database
```

The command prompts for MySQL or SQLite and writes the appropriate settings to `config/database.php`.

## Configuration

Create `config/database.php` with the required constants:

```php
<?php
// config/database.php

define('DB_TYPE', 'mysql');          // or 'sqlite'
define('DB_HOST', 'localhost');
define('DB_NAME', 'app');
define('DB_USER', 'user');
define('DB_PASSWORD', 'secret');
```

The file is automatically loaded from `config/config.php` when present. Avoid committing credentials to version control.

## Error Handling

- `DatabaseHandler` logs warnings if required constants are missing or an unsupported driver is used.
- Wrap query calls in `try/catch` blocks and log exceptions to avoid exposing details:

```php
use CorianderCore\Core\Database\SQLManager;

try {
    $users = SQLManager::findAll('*', 'users');
} catch (\Throwable $e) {
    // handle or log error
}
```

## Best Practices

- Use prepared statements and parameter binding to prevent SQL injection.
- Keep long-lived connections to a minimum; enable `DatabaseHandler::setAutoCloseConnection(false)` only when necessary.
- Centralize complex queries in repository classes to maintain SOLID principles.
- Close connections explicitly in long-running scripts.

