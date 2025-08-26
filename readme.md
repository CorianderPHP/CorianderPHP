# CorianderPHP: Lightweight, Modular PHP Framework

**CorianderPHP** is a minimalist PHP framework focused on modularity and performance. The core (CorianderCore) can be updated independently of your application code, allowing seamless upgrades without breaking customizations.

---

## Features

- **Modular Architecture**: Extend the core with custom modules while keeping the base lightweight.
- **Easy to Extend**: Build custom modules without modifying core files (see [Modules](docs/modules.md)).
- **Minimal External Dependencies**: Only essential packages for logging, routing, and testing (PSR-3/7/15).
- **CLI Tooling**: Run `php coriander` to manage views, controllers, databases and more.
- **Performance**: Lean code and efficient asset handling.
- **NodeJS Integration**: Built-in TypeScript and TailwindCSS tooling.

## Documentation

Detailed guides live in the [`docs/`](docs) directory:

- [CLI](docs/cli.md)
- [Routing](docs/routing.md)
- [Views](docs/views.md)
- [Controllers](docs/controllers.md)
- [Database](docs/database.md)
- [Sitemap](docs/sitemap.md)
- [Cache](docs/cache.md)
- [Modules](docs/modules.md)
- [NodeJS Integration](docs/nodejs.md)

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
4. Watch or build assets as needed (see [NodeJS Integration](docs/nodejs.md)).

### Logging

Configure the PSR-3 logger using environment variables:

- `LOG_CHANNEL`: `stderr`, `stdout` or a file path (default: `stderr`)
- `LOG_LEVEL`: minimum level to record (default: `warning`)

## CLI Quick Reference

All commands are invoked via `php coriander`:

- `php coriander make:view Home` – create a view ([Views](docs/views.md)).
- `php coriander make:controller Home` – create a controller ([Controllers](docs/controllers.md)).
- `php coriander make:database` – interactive database setup ([Database](docs/database.md)).
- `php coriander make:sitemap` – generate sitemap helper ([Sitemap](docs/sitemap.md)).
- `php coriander cache controllers|all|clear` – manage caches ([Cache](docs/cache.md)).
- `php coriander nodejs run build-ts` – compile TypeScript ([NodeJS Integration](docs/nodejs.md)).
- `\CorianderCore\Core\Image\ImageHandler::render()` – convert images to WebP in views ([Views](docs/views.md)).
- Place reusable packages under `CorianderCore/modules` to create custom modules ([Modules](docs/modules.md)).

## CSRF Protection

CorianderPHP ships with a token-based CSRF guard. Include the token inside forms:

```php
<form method="POST" action="/submit">
    <?= \CorianderCore\Core\Security\Csrf::input() ?>
    <!-- your fields -->
    <button type="submit">Send</button>
</form>
```

Controllers validate tokens using `Csrf::validateRequest()` or `Csrf::validate()` for JSON payloads.

## Development Status

CorianderPHP is under active development. Check the documentation for the latest features and updates.

## Contributing

We welcome contributions. Please submit pull requests or report issues on GitHub.

## License

This project is licensed under the MIT License.

