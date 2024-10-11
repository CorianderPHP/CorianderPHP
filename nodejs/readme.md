# CorianderPHP NodeJS Integration

This directory houses the **NodeJS** runtime environment for managing and building **TypeScript** and **TailwindCSS** assets in the **CorianderPHP** framework. This setup helps provide modern front-end development capabilities within the CorianderPHP ecosystem.

---

## Features

- **TypeScript Compilation**: Automatically compiles `.ts` files into JavaScript, with separate files for each page.
- **TailwindCSS Processing**: Compiles TailwindCSS for styling and generates minified CSS for production use.
- **Watchers**: Includes development watchers to monitor and auto-rebuild TypeScript and CSS files on changes.
- **Build for Production**: Optimizes both TypeScript and TailwindCSS files for production environments.
  
## Installation

Before working with the **NodeJS** features in CorianderPHP, ensure you’ve installed the necessary dependencies:

- Install NodeJS dependencies (run this from the root of the project):
   ```bash
   php coriander nodejs run install
   ```

---

## Available Scripts

The following npm scripts are available in the `package.json` file to manage your assets. **All commands must be executed from the root of the project**:


- **Watch TypeScript**: Watches and compiles TypeScript files from the `src/` folder. (Run this in a dedicated terminal)
   ```bash
   php coriander nodejs run watch-ts
   ```
- **Build TypeScript**: Compiles all TypeScript files once.
   ```bash
   php coriander nodejs run build-ts
   ```
- **Watch TailwindCSS**: Watches and compiles CSS using TailwindCSS. (Run this in a dedicated terminal)
   ```bash
   php coriander nodejs run watch-tw
   ```
- **Build TailwindCSS**: Compiles CSS with TailwindCSS and minifies it.
   ```bash
   php coriander nodejs run build-tw
   ```
- **Build All (Development)**: Compiles both TypeScript and TailwindCSS but **only minifies the CSS**. JavaScript is not minified in this mode.
   ```bash
   php coriander nodejs run build-all
   ```
- **Build for Production**: Compiles and **minifies both JavaScript and CSS** for production use.
   ```bash
   php coriander nodejs run build-all
   ```

---

## TypeScript Structure

Each page in **CorianderPHP** can have its own TypeScript file located inside the `src/` folder. For example:
- `nodejs/src/home/index.ts` → Outputs to `public/assets/js/home/index.js`

In this setup, all dependencies for a page are bundled into a single output file. For instance, if `home/index.ts` requires utility files like `Responsive.ts` and `Search.ts`, the output will be **one bundled JavaScript** file containing all the necessary code and dependencies for that page.

### Exemple
- `nodejs/src/home/index.ts`
    - Requires: `nodejs/src/Utils/Responsive.ts`
    - Requires: `nodejs/src/home/Search/Search.ts`
The output will be:
- `public/assets/js/home/index.js` (bundling all the required files).

---

## TailwindCSS Structure

The input CSS file for TailwindCSS is located at:
- `public/assets/css/input.css`

The output file is generated at:
- `public/assets/css/output.css`

---

## Custom Build Configuration

The build and watch scripts utilize **esbuild** and **TailwindCSS** to create an optimized build process. The configuration files (`build.js`, `build.min.js`, `build.watch.js`) are already set up to handle your TypeScript and TailwindCSS workflows efficiently.

### Important Note on Minification:
- The `build-all` script **only minifies the CSS** output. JavaScript files will not be minified in this build.
- The `build-prod` script minifies both JavaScript and CSS for full production optimization.

---

## Development Workflow

1. **Start the TypeScript watcher** in a **dedicated terminal** to automatically compile `.ts` files as you work:
   ```bash
   php coriander nodejs run watch-ts
   ```

2. **Start the TailwindCSS watcher** in a **dedicated terminal** to auto-compile CSS changes:
   ```bash
   php coriander nodejs run watch-tw
   ```

3. **Build for Production** when you’re ready to deploy:
   ```bash
   php coriander nodejs run build-prod
   ```

| Important: Always run the commands from the root of the CorianderPHP project.

---

## Contribution

If you'd like to contribute to the **NodeJS** integration or improve the build pipeline, please submit pull requests or open an issue directly in the **CorianderPHP** repository.

---

## License

This project is licensed under the MIT License.