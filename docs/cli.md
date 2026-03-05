# CLI Guide

CorianderPHP provides a command-line interface accessible via `php coriander` for scaffolding, maintenance, and framework updates.

## Usage

Invoke commands from the project root:

```bash
php coriander <command> [arguments]
```

Examples:

- `php coriander make:view Home`
- `php coriander make:migration CreateUsersTable`
- `php coriander migrate:status`
- `php coriander cache controllers`
- `php coriander nodejs run build-ts`
- `php coriander version`
- `php coriander update --dry-run`

## Command Reference

### `version`

Print the locally installed framework version from `CorianderCore/VERSION`.

```bash
php coriander version
```

### `update`

Checks GitHub for the newest framework release (fallback to latest tag) and updates framework-managed files.

```bash
php coriander update
```

Behavior:

- Shows current and latest version before applying updates.
- Asks for confirmation in interactive mode.
- Updates only managed framework paths (`CorianderCore` and `coriander`).
- Protects local modified files by skipping them unless `--force` is used.
- Creates `.bak` backups before overwriting managed files.
- Automatically rolls back applied files if an update operation fails mid-way.
- Runs post-update tasks (`composer dump-autoload`).
- Prints a summary of planned/applied/skipped changes.
- Retries transient GitHub API failures and reports rate-limit errors clearly.
- Validates `--backup-dir` as a safe relative path (no absolute paths or `..` traversal segments).
- Enforces updater policy guard (environment, optional auth token, optional rate limit).

#### Flags

- `--yes`: skip confirmation and apply update directly.
- `--dry-run`: preview the update plan without writing files.
- `--force`: overwrite files detected as locally modified.
- `--clear-cache`: run `php coriander cache clear` after update.
- `--backup-dir=backups/custom`: override backup output directory for this run (must stay inside project).
- `--auth-token=<token>`: required only when `CORIANDER_UPDATER_AUTH_TOKEN` is configured.

Updater environment variables:

- `CORIANDER_UPDATER_ENABLED` (`1`/`0`, default `1`)
- `CORIANDER_UPDATER_ALLOW_PRODUCTION` (`1` to allow in `APP_ENV=production`, default deny)
- `CORIANDER_UPDATER_AUTH_TOKEN` (shared token for guarded environments)
- `CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR` (default `5`)
- `CORIANDER_UPDATER_RATE_LIMIT_FILE` (optional custom state file)
- `CORIANDER_UPDATE_ALLOWED_REPOS` (repo allowlist, comma separated)

Examples:

```bash
php coriander update --yes
php coriander update --dry-run
php coriander update --yes --force
php coriander update --yes --clear-cache
php coriander update --yes --backup-dir=backups/custom
php coriander update --yes --auth-token=your-token
```

### `make:migration`

Create a timestamped migration file under `database/migrations`.

```bash
php coriander make:migration CreateUsersTable
```

### `migrate`

Apply pending migrations tracked in the `migrations` table.

```bash
php coriander migrate
```

Flags:

- `--dry-run`: show pending migrations without applying them.
- `--allow-changed`: allow running when an already-applied migration file checksum changed (local/dev only).

### `migrate:status`

Show migration status (`applied` or `pending`) and batch numbers.

```bash
php coriander migrate:status
```

### `migrate:rollback`

Rollback latest migration batch (or multiple batches).

```bash
php coriander migrate:rollback
php coriander migrate:rollback --step=2
```

Flags:

- `--step=N`: rollback N latest batches (default: `1`).
- `--dry-run`: preview rollback targets without changing the database.

## Error Handling

- Commands print diagnostic messages prefixed with `[Error]` or `[Warning]` when something goes wrong.
- Most commands exit silently on success and non-zero on failure, allowing usage in scripts.

## Best Practices

- Run the CLI from the project root so generated files resolve to correct paths.
- Inspect output carefully; many commands provide hints for missing dependencies or misconfigurations.
- Rebuild caches (`php coriander cache controllers`) after adding controllers or clearing the `cache/` directory.
- Use `php coriander update --dry-run` before production updates.
- In shared/production environments, do not edit applied migration files.
