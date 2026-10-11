# CLI Guide

CorianderPHP provides a command-line interface accessible via `php coriander` for scaffolding, maintenance, and framework updates.

## Usage

Invoke commands from the project root:

```bash
php coriander <command> [arguments]
```

Use `php coriander` for a short command overview. Use `php coriander help` for
detailed command descriptions and examples.

Examples:

- `php coriander`
- `php coriander help`
- `php coriander help make`
- `php coriander nodejs --help`
- `php coriander make:view Home`
- `php coriander make:route admin`
- `php coriander make:migration CreateUsersTable`
- `php coriander migrate:status`
- `php coriander cache clear`
- `php coriander nodejs run build-ts`
- `php coriander nodejs run build-prod`
- `php coriander version`
- `php coriander update --dry-run`

## Command Reference

### `help`

Show CLI usage, command descriptions, and examples.

```bash
php coriander help
php coriander help make
php coriander help nodejs
```

Help flags are also supported:

```bash
php coriander --help
php coriander -h
php coriander make --help
php coriander nodejs --help
```

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
- Protects Git-detected local modifications, renames, deletions, and untracked
  files by skipping affected paths unless `--force` is used. Git must be available
  and the installation must be a Git checkout for this detection.
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
- `--pre-release`: allow updating to the latest GitHub pre-release. Stable releases are preferred by default; if no stable release exists, the updater falls back to the latest pre-release and prints a warning.
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
php coriander update --yes --pre-release
php coriander update --yes --backup-dir=backups/custom
php coriander update --yes --auth-token=your-token
```

### `routes:list`

List discovered methods, URL patterns, relative handlers and inherited middleware filenames in root-to-child order. `HEAD (GET)` marks a GET fallback; an explicit HEAD handler gets its own row. The list is sorted by path/method and reflects current files, even when a production route cache exists. It does not execute handlers or middleware, inspect their classes, build the cache or send HTTP requests.

```bash
php coriander routes:list
php coriander help routes
```

Empty route directories print `No routes found.`; discovery errors return exit code 1 and invalid arguments return 2.

### `make:migration`

Create a timestamped migration file under `database/migrations`.

```bash
php coriander make:migration CreateUsersTable
```

### `make:route`

Generate a method handler such as `src/Views/users/[id].post.php` with `php coriander make:route "users/[id].post"`. Omitting the suffix defaults to GET. Files are discovered automatically; no registration is required. `make:view admin/profile` creates `src/Views/admin/profile.php`. Both generators are optional.

The route generator shares discovery's filename and parameter validation. Invalid syntax or private names return exit code 2. Conflicting routes or invalid existing definitions return 1. Repeated parameters, ambiguous parameter names and duplicate `index` aliases are rejected before creating directories or writing files. Validation reads current filenames without executing application code or updating production caches.

Generated views contain a section and heading. Add your own content; a form is not generated automatically. When adding a mutating form, include `Csrf::input()` and create the corresponding method handler.

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
- Commands return `0` on success and non-zero on command errors, allowing usage in scripts and CI.
- Execution failures return `1`.
- Invalid usage or bad arguments return `2`.
- Unknown commands or subcommands return `3`.
- Commands that wrap external processes, such as `nodejs`, propagate the external process exit code when possible.

## Best Practices

- Run the CLI from the project root so generated files resolve to correct paths.
- Inspect output carefully; many commands provide hints for missing dependencies or misconfigurations.
Route maps rebuild automatically; cache clear is optional. Controllers and manual registration were removed in 0.3.0.
- Use `php coriander update --dry-run` before production updates.
- In shared/production environments, do not edit applied migration files.
