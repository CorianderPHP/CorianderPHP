# Environment Variables

CorianderPHP loads simple runtime variables from `.env`.

When `.env` is missing and `.env-example` exists, the framework copies `.env-example` to `.env` before loading variables. The generated `.env` file is ignored by Git.

## Supported Syntax

```env
APP_ENV=local
APP_DEBUG=1
APP_TIMEZONE=Europe/Paris
PROJECT_URL=http://localhost
PUBLIC_URL_PREFIX=
```

Supported:

- empty lines
- comments starting with `#`
- `KEY=value`
- `export KEY=value`
- single-quoted values
- double-quoted values with `\n`, `\r`, `\t`, `\"`, and `\\`
- inline comments after unquoted values, such as `APP_ENV=local # comment`

The loader does not parse complex shell syntax, arrays, multiline values, or variable expansion.

## Override Behavior

Existing server or shell environment variables are not overwritten by `.env`.

This keeps production deployment variables in control while preserving a simple local developer workflow.
