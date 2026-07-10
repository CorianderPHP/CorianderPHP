# Database Module Guide

The database layer centralizes connection handling through `DatabaseHandler` instances registered in a service container and exposes helper methods via `SQLManager`.

## Creating Configuration via CLI

Generate a database configuration interactively:

```bash
php coriander make:database
```

The command prompts for MySQL or SQLite and writes the appropriate settings to `config/database.php`.

## Configuration

Database settings can come from `.env` or `config/database.php`.

For local projects, `.env` is usually enough:

```env
DB_TYPE=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_CHARSET=utf8mb4
DB_NAME=app
DB_USER=user
DB_PASSWORD=secret
```

For projects that need PHP-level configuration, create `config/database.php` with the required constants:

```php
<?php
// config/database.php

define('DB_TYPE', 'mysql');          // or 'sqlite'
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');
define('DB_NAME', 'app');
define('DB_USER', 'user');
define('DB_PASSWORD', 'secret');
```

The file is automatically loaded from `config/config.php` when present. Constants already defined in `config/database.php` take precedence over `.env` values. Avoid committing credentials to version control.

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
- Use `sqlScript` when a repository needs custom SQL such as joins, aggregates, or multi-table reads.
- `findBy`, `update`, and `deleteFrom` remain available for compatibility but are deprecated for routine use.
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

## Custom SQL

Use `sqlScript` when a repository needs free SQL for joins, aggregates, reports, or custom filtering that does not fit the table-oriented helpers.

SELECT-like statements return data based on the number of rows found:

- no rows: `[]`
- one row: one associative array
- multiple rows: a list of associative arrays

```php
use CorianderCore\Core\Database\SQLManager;

$topics = SQLManager::sqlScript(
    'SELECT topics.id, topics.title, users.username
     FROM topics
     JOIN users ON users.id = topics.user_id
     WHERE topics.status = :status
     ORDER BY topics.created_at DESC',
    ['status' => 'published']
);
```

For one row, write the query naturally and use the returned associative array:

```php
$topic = SQLManager::sqlScript(
    'SELECT id, title FROM topics WHERE id = :id',
    ['id' => $topicId]
);
```

For a single value, select an alias and read it from the first row:

```php
$row = SQLManager::sqlScript('SELECT COUNT(*) AS total FROM topics');

$total = (int) ($row['total'] ?? 0);
```

Write statements return `true` when execution succeeds:

```php
SQLManager::sqlScript(
    'UPDATE topics SET locked = :locked WHERE id = :id',
    ['locked' => 1, 'id' => $topicId]
);
```

For simple equality-based CRUD, prefer the table helpers:

```php
SQLManager::findWhere(['id', 'title'], 'topics', ['status' => 'published']);
SQLManager::updateWhere('topics', ['locked' => 1], ['id' => $topicId]);
SQLManager::deleteWhere('topics', ['status' => 'draft']);
```



