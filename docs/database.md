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

## Migrations

CorianderPHP supports timestamped migration files with batch tracking.

### Create migration files

```bash
php coriander make:migration CreateUsersTable
```

This creates a file in `database/migrations` named like:

`20260305123000_create_users_table.php`

Each migration file returns an object with `up(PDO $pdo)` and optional `down(PDO $pdo)` methods.

### Apply migrations

```bash
php coriander migrate
```

### Check migration status

```bash
php coriander migrate:status
```

### Rollback migrations

```bash
php coriander migrate:rollback
php coriander migrate:rollback --step=2
```

### Migration safety notes

- Executed migration checksums are tracked in the `migrations` table.
- If an executed migration file changes, commands fail by default.
- Use `--allow-changed` only in local development when intentionally editing history.
- Keep migrations immutable in shared/staging/production environments.

## Error Handling

- `DatabaseHandler` logs warnings if required constants are missing, unsupported drivers are used, or the connection cannot be established.
- Wrap query calls in `try/catch` blocks and log exceptions to avoid exposing details:

```php
use CorianderCore\Core\Database\SQLManager;
use CorianderCore\Core\Database\DatabaseException;

try {
    $users = SQLManager::findAll('users');
} catch (DatabaseException $e) {
    // handle or log error
}
```

## Best Practices

- Use prepared statements and parameter binding to prevent SQL injection.
- Prefer `findWhere`, `updateWhere`, and `deleteWhere` when your conditions are simple equality checks.
- `findBy`, `update`, and `deleteFrom` remain available for advanced SQL conditions but are deprecated for routine use.
- Keep long-lived connections to a minimum; enable `DatabaseHandler::setAutoCloseConnection(false)` only when necessary.
- Centralize complex queries in repository classes to maintain SOLID principles.
- Close connections explicitly in long-running scripts.

## Usage Examples

Use `findAll(['col1', 'col2'], $table)` when you want an explicit column list.
`findAll($table)` is the concise all-columns signature.
`findAll(['*'], $table)` remains available for compatibility but is not recommended.

### Selecting Records

```php
use CorianderCore\Core\Database\SQLManager;

$activeUsers = SQLManager::findWhere(
    ['id', 'email'],
    'users',
    ['status' => 'active']
);
```
Fetches the ID and email for every user marked as active.

### Inserting Records

```php
use CorianderCore\Core\Database\SQLManager;

SQLManager::insertInto('users', [
    'email' => 'john@example.com',
    'status' => 'active',
]);
```
Creates a user record with the provided email and marks it as active.

### Updating Records

```php
use CorianderCore\Core\Database\SQLManager;

SQLManager::updateWhere('users', ['status' => 'disabled'], ['id' => 5]);
```
Disables the user whose ID equals `5`.

### Deleting Records

```php
use CorianderCore\Core\Database\SQLManager;

SQLManager::deleteWhere('users', ['status' => 'inactive']);
```
Removes all users currently flagged as inactive.

## Advanced Conditions

For non-equality expressions (`IN`, range queries, SQL functions), use the lower-level methods with explicit placeholders (deprecated for routine use):

```php
SQLManager::findBy(
    ['id', 'email'],
    'users',
    'created_at >= :from AND status IN (:s1, :s2)',
    ['from' => '2026-01-01', 's1' => 'active', 's2' => 'pending']
);
```


