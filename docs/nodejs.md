# NodeJS Integration Guide

CorianderPHP provides NodeJS tooling for compiling TypeScript and TailwindCSS assets.

## Requirements

Install Node.js with npm before using the NodeJS commands. On Windows, the official
installer usually provides npm at:

```powershell
C:\Program Files\nodejs\npm.cmd
```

If you use a portable or custom Node.js installation, make sure npm is included and
available from your shell. You can verify which npm executable is used with:

```powershell
where.exe npm
Get-Command npm -All
```

With the official Windows installer, npm is normally found under
`C:\Program Files\nodejs\`. A portable installation should resolve npm from the
portable Node.js directory, for example:

```text
F:\dev\nodejs\npm.cmd
```

The exact internal npm files can vary between Node.js/npm versions. Do not rely
on internal paths such as `node_modules\npm\bin\npm-cli.js` for normal setup
checks. Instead, verify that `npm -v` works and that `where.exe npm` or
`Get-Command npm -All` points first to the Node.js installation you intend to use.

## Installing Dependencies

Install packages defined in `package.json`:

```bash
php coriander nodejs install
```

Add a new package (e.g. `axios`):

```bash
php coriander nodejs install axios
```

Coriander runs npm internally from the project `nodejs/` directory, so the command
above is equivalent to running `npm install` there. Do not include `npm` in the
Coriander command; `php coriander nodejs npm install` would try to run
`npm npm install`.

## Available Scripts

Run all commands from the project root.

- **Watch TypeScript**
  ```bash
  php coriander nodejs run watch-ts
  ```
- **Watch TypeScript and TailwindCSS**
  ```bash
  php coriander nodejs run watch-all
  ```
- **Build TypeScript**
  ```bash
  php coriander nodejs run build-ts
  ```
- **Watch TailwindCSS**
  ```bash
  php coriander nodejs run watch-tw
  ```
- **Build TailwindCSS**
  ```bash
  php coriander nodejs run build-tw
  ```
- **Build All (development)**
  ```bash
  php coriander nodejs run build-all
  ```
- **Build for Production**
  ```bash
  php coriander nodejs run build-prod
  ```

## Project Layout

- TypeScript source: `nodejs/src`
- Compiled JavaScript: `public/assets/js`
- Tailwind input: `public/assets/css/input.css`
- Tailwind output: `public/assets/css/output.css`

## Best Practices

- Commit only compiled assets needed for production; ignore `node_modules/`.
- Run `install` after pulling changes to ensure packages are up to date.
- For troubleshooting, run the underlying npm command directly inside the `nodejs` directory.

## Troubleshooting Windows npm Resolution

Coriander resolves npm automatically. If a custom or portable Node.js installation
causes npm to resolve incorrectly, point Coriander to the npm launcher explicitly:

```powershell
$env:CORIANDER_NPM_EXECUTABLE = "C:\Program Files\nodejs\npm.cmd"
php coriander nodejs install
```

You can also use `NPM_EXECUTABLE` if you already rely on that environment variable:

```powershell
$env:NPM_EXECUTABLE = "C:\Program Files\nodejs\npm.cmd"
php coriander nodejs install
```

Inspect the executables found by your shell:

```powershell
where.exe node
where.exe npm
Get-Command npm -All
```

If npm fails with a path like `project\nodejs\node_modules\npm\bin\npm-cli.js`,
the npm launcher is probably resolving npm internals relative to the project
directory instead of the Node.js installation. Check the first result from
`where.exe npm`, repair the Node.js installation if needed, or set
`CORIANDER_NPM_EXECUTABLE` to the intended `npm.cmd` path.

