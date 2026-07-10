# CorianderPHP: Lightweight, Modular PHP Framework

**CorianderPHP** is a minimalist PHP framework focused on modularity and performance. The core (CorianderCore) can be updated independently of your application code, allowing seamless upgrades without breaking customizations.

---

## Features

- **Modular Architecture**: Extend the core with custom modules while keeping the base lightweight.
- **Easy to Extend**: Build custom modules without modifying core files (see [Modules](docs/modules.md)).
- **Minimal External Dependencies**: Only essential packages for logging, routing, and testing (PSR-3/7/15).
- **CLI Tooling**: Run `php coriander` to manage views, controllers, databases, and framework updates.
- **Performance**: Lean code and efficient asset handling.
- **NodeJS Integration**: Built-in TypeScript and TailwindCSS tooling.

## Documentation

Detailed guides live in the [docs/](docs) directory. Start with [Documentation Index](docs/index.md):

- [CLI](docs/cli.md)
- [Routing](docs/routing.md)
- [Views](docs/views.md)
- [Controllers](docs/controllers.md)
- [Middleware](docs/middleware.md)
- [Database](docs/database.md)
- [Sitemap](docs/sitemap.md)
- [Cache](docs/cache.md)
- [Modules](docs/modules.md)
- [NodeJS Integration](docs/nodejs.md)
- [Security](docs/security.md)

These guides are kept in sync with releases; review them when upgrading.

## Getting Started

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/CorianderPHP/CorianderPHP.git
   ```
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Install NodeJS dependencies or add packages:
   ```bash
   php coriander nodejs install             # install from package.json
   php coriander nodejs install tailwindcss # add a package
   ```
   Requires Node.js with npm available. On Windows, npm is usually installed at
   `C:\Program Files\nodejs\npm.cmd`; custom installs can be configured with
   `CORIANDER_NPM_EXECUTABLE` (see [NodeJS Integration](docs/nodejs.md)).
4. Watch or build assets as needed (see [NodeJS Integration](docs/nodejs.md)).

### Logging

Configure the PSR-3 logger using environment variables:

- `LOG_CHANNEL`: `stderr`, `stdout` or a file path (default: `stderr`)
- `LOG_LEVEL`: minimum level to record (default: `warning`)
- `LOG_FORMAT`: `line` or `json` (default: `json` in production)
- `LOG_MAX_FILE_BYTES`: rotate file channel at this size (default: `10485760`)
- `LOG_MAX_FILES`: number of rotated files to keep (default: `5`)

Database note: prefer `findWhere`, `updateWhere`, and `deleteWhere`; raw-string helpers `findBy`, `update`, and `deleteFrom` are deprecated for routine usage.
Use `findAll(['col1', 'col2'], $table)` to request specific columns.
Use `findAll($table)` for all columns.
`findAll(['*'], $table)` remains supported for compatibility but is not recommended.
Use `sqlScript($sql, $params)` for custom SQL such as joins, aggregates, and repository-owned queries; SELECT-like statements return `[]`, one associative row, or multiple rows depending on the result size, and write statements return `true` on success.

## CLI Quick Reference

All commands are invoked via `php coriander`:

- `php coriander make:view Home` - create a view ([Views](docs/views.md)).
- `php coriander make:controller Home` - create a controller ([Controllers](docs/controllers.md)).
- `php coriander make:route admin` - create a route file under `src/Routes` for larger route sets ([Routing](docs/routing.md)).
- `php coriander make:database` - interactive database setup ([Database](docs/database.md)).
- `php coriander make:migration CreateUsersTable` - create a migration file ([Database](docs/database.md)).
- `php coriander migrate` - apply pending migrations ([Database](docs/database.md)).
- `php coriander migrate:status` - view migration state ([Database](docs/database.md)).
- `php coriander migrate:rollback --step=1` - rollback latest migration batch ([Database](docs/database.md)).
- `php coriander make:sitemap` - generate sitemap helper ([Sitemap](docs/sitemap.md)).
- `php coriander cache controllers|all|clear` - manage caches ([Cache](docs/cache.md)).
- `php coriander nodejs run build-ts` - compile TypeScript ([NodeJS Integration](docs/nodejs.md)).
- `php coriander version` - display installed framework version.
- `php coriander update` - update framework managed files (asks confirmation by default).
- `php coriander update --yes` - update without interactive prompt.
- `php coriander update --dry-run` - show planned framework changes without writing files.
- `php coriander update --force` - overwrite locally modified framework-managed files.
- `php coriander update --clear-cache` - clear framework cache after update.
- `php coriander update --pre-release` - allow updating to GitHub pre-releases.
- `php coriander update --backup-dir=backups/custom` - override backup output directory for this run (relative path only, no `..`).
- `php coriander update --auth-token=<token>` - provide updater token when guard is enabled.
- `php coriander update --rollback` - restore framework-managed files from the latest backup scope.
- `\CorianderCore\Core\Image\ImageHandler::render()` - convert images to WebP in views ([Views](docs/views.md)).
- Runtime variables load from `.env`; `.env-example` is copied automatically when `.env` is missing ([Environment](docs/environment.md)).
- Place project middleware under `src/Middleware` ([Middleware](docs/middleware.md)).
- Place project modules under `src/Modules`; `CorianderCore/modules` is reserved for framework-owned modules ([Modules](docs/modules.md)).

## Framework Update Notes

- Framework updates are fetched from GitHub releases (or latest tag fallback), with retry handling for transient API/network failures.
- Stable releases are preferred by default. Use `--pre-release` to allow pre-release updates; if no stable release exists, the updater falls back to the latest pre-release and prints a warning.
- Updater execution can be restricted with environment policy (`CORIANDER_UPDATER_ENABLED`, `CORIANDER_UPDATER_ALLOW_PRODUCTION`, optional `CORIANDER_UPDATER_AUTH_TOKEN`, and rate-limit controls).
- Only framework-managed paths are updated by the updater, and partial failures trigger automatic rollback.
- Backups are written under `backups/coriander/<from-version>-to-<to-version>/` by default (configurable via `CORIANDER_UPDATE_BACKUP_DIR` in `config/config.php`).
- Rollback restores from the most recent backup scope detected in the configured backup directory.
- Rollback also removes files that were originally added by the update (when rollback metadata is available).
- Start with a first release tag such as `v0.1.0` before using `php coriander update` in other projects.
- Rollback is atomic: if restoration fails mid-way, the framework files are restored back to their pre-rollback state.

## CSRF Protection

CorianderPHP ships with a token-based CSRF guard. Include the token inside forms:

```php
<form method="POST" action="/submit">
    <?= \CorianderCore\Core\Security\Csrf::input() ?>
    <!-- your fields -->
    <button type="submit">Send</button>
</form>
```

Controllers validate tokens using `Csrf::validateRequest()` or `Csrf::validate()` for JSON payloads. By default, middleware validation applies to mutating web methods (`POST`, `PUT`, `PATCH`, `DELETE`) and accepts tokens from parsed bodies and standard `application/x-www-form-urlencoded`/`application/json` request bodies.

Behind a reverse proxy, configure TRUSTED_PROXIES so secure-cookie HTTPS detection trusts only known proxy addresses.

## Development Status

CorianderPHP is under active development. Check the documentation for the latest features and updates.

## Contributing

We welcome contributions. Please submit pull requests or report issues on GitHub.

## License

This project is licensed under the MIT License.


