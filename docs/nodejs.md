# NodeJS Integration Guide

CorianderPHP provides NodeJS tooling for compiling TypeScript and TailwindCSS assets.

## Requirements

Use Node.js 24 LTS with npm for asset tooling; CI builds on Linux and Windows with this version. Node.js 22 remains supported. PHP-only applications do not require Node.js. On Windows, the official
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
  Runs TypeScript compilation, bundles/minifies JavaScript, and generates
  minified TailwindCSS output.

## Project Layout

- TypeScript source: `nodejs/src`
- Compiled JavaScript: `public/assets/js`
- Tailwind input: `public/assets/css/input.css`
- Tailwind output: `public/assets/css/output.css`

## Best Practices

- Commit only compiled assets needed for production; ignore `node_modules/`.
- Run `install` after pulling changes to ensure packages are up to date.
- For troubleshooting, run the underlying npm command directly inside the `nodejs` directory.

## Dependency security

The starter pins Tailwind CSS and CLI to 4.3.0. CLI 4.3.3 pins `@parcel/watcher` 2.5.1, which pulls in unpatched vulnerable `braces`. The lockfile uses watcher 2.6.0; keep the Tailwind pin until the upstream dependency is fixed. TypeScript stays on major 5 and esbuild stays on 0.28.

Use the committed `nodejs/package-lock.json` for reproducible installs. From `nodejs`, run `npm ci` after updating the lockfile and `npm audit` to check advisories. CI audits all asset dependencies, including development dependencies, and builds both development and production assets on Linux and Windows.

The 0.3.0 lockfile includes `source-map-js` 1.2.2, fixing CVE-2026-93749 in the transitive Tailwind toolchain dependency. Existing projects should copy the updated lockfile if their `package.json` matches the starter, or run `npm update source-map-js` in their own `nodejs` directory, then rebuild assets. The PHP runtime does not require Node.js.

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

