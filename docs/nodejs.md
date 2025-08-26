# NodeJS Integration Guide

CorianderPHP provides NodeJS tooling for compiling TypeScript and TailwindCSS assets.

## Installing Dependencies

Install packages defined in `package.json`:

```bash
php coriander nodejs install
```

Add a new package (e.g. `axios`):

```bash
php coriander nodejs install axios
```

## Available Scripts

Run all commands from the project root.

- **Watch TypeScript**
  ```bash
  php coriander nodejs run watch-ts
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

