# CorianderPHP: Lightweight, Modular PHP Framework

**CorianderPHP** is a minimalist PHP framework designed to help developers build applications faster, with a focus on modularity and simplicity. It provides a core foundation, CorianderCore, that can be updated independently of your application code, ensuring seamless upgrades without breaking your customizations.

This framework aims to offer essential functionalities to streamline development, avoiding the bloat of larger frameworks by only including what you need. It’s an ideal starting point for developers who want a clean slate and the flexibility to choose their dependencies, without the overhead of an all-purpose framework.

---

## Features

- **Modular Architecture**: The core code can be extended with custom modules, keeping the base lightweight and focused on performance.
- **Easy to Extend**: Add your own modules and features without modifying the core, ensuring future compatibility.
- **No External Dependencies**: CorianderPHP doesn't impose unnecessary libraries, enabling you to select only the packages you need.
- **CLI Tooling**: Includes a custom command-line interface (CLI) for generating views, controllers, database configuration, ect...
- **Performance**: Focuses on optimizing performance and reducing environmental impact through lean code and efficient asset handling.
- **NodeJS Integration**: Preconfigured TypeScript and TailwindCSS support for modern frontend development.
- **Development Watchers**: Automatically watch and rebuild assets (TypeScript, CSS) when files are modified during development.

---

## Installation

To set up **CorianderPHP**, follow these steps:

1. Clone the repository to your local environment:
   ```bash
   git clone https://github.com/CorianderPHP/CorianderPHP.git
   ```
2. Install PHP dependencies via Composer:
   ```bash
   composer install
   ```
3. Install NodeJS dependencies:
   ```bash
   php coriander nodejs install
   ```
4. Set up your TypeScript and TailwindCSS assets with the available watchers:
   ```bash
   php coriander nodejs run watch-ts    # To watch and compile TypeScript
   php coriander nodejs run watch-tw    # To watch and compile TailwindCSS
   ```

---

## Command-Line Interface (CLI)

CorianderPHP provides a custom CLI tool to help manage views, controllers, and Node.js tasks:

### Creating Views

Generate a new view using the `make:view` command:
   ```bash
   php coriander make:view contactMe
   ```
This creates a new view in `public/public_views/contact-me/` using the CorianderPHP templates for view and metadata.

### Creating Controllers

Generate a new controller using the `make:controller` command:
   ```bash
   php coriander make:controller contactMe
   ```
This creates a new controller in `src/Controllers/ContactMeController.php` using the CorianderPHP templates.

The controller will include methods like `index()` for the default page, `show($id)` for displaying specific items, and `store()` for handling form submissions.

### Creating Databases

Create a new database configuration using the `make:database` command:
   ```bash
   php coriander make:database
   ```
You will be prompted to choose between MySQL and SQLite, and the appropriate configuration file will be generated in the `config` folder.

### Creating Sitemap

Generate a new sitemap file using the `make:sitemap` command:
   ```bash
   php coriander make:sitemap
   ```
This creates a sitemap.php file in the `public/` directory. The sitemap script:
- Checks if a `sitemap.xml` exists and if it was created today. If not, it regenerates the file.
- Fetches static pages automatically.
- Allows you to manually add dynamic pages (e.g., blog posts).
- Outputs the `sitemap.xml` with the correct XML headers for SEO.
You can customize the generated `sitemap.php` file to fit your needs by adding dynamic URLs or adjusting priorities.

---

## NodeJS Integration

**CorianderPHP** tightly integrates NodeJS, TypeScript, and TailwindCSS to support modern front-end development practices:

- **TypeScript**: Compiles `.ts` files from the `nodejs/src` directory into JavaScript. Each page should have an `index.ts` file within its respective folder (e.g., `nodejs/src/home/index.ts`) to generate JavaScript specific to that page. Additional utility or class files can be placed in other folders (e.g., `nodejs/src/Utils/Responsive.ts` or `nodejs/src/home/Search/Search.ts`) without needing an `index.ts`.

- **TailwindCSS**: Processes CSS from `public/assets/css/input.css` and outputs a minified CSS file to `public/assets/css/output.css` for production.

### Asset Management Commands

#### Watch TypeScript
   ```bash
   php coriander nodejs run watch-ts
   ```

#### Watch TailwindCSS
   ```bash
   php coriander nodejs run watch-tw
   ```

#### Build for Production
   ```bash
   php coriander nodejs run build-all
   ```

---

## Development Status

**CorianderPHP** is under active development. Some features, such as WebP image optimization and sitemap creation tools, are currently being implemented. Comprehensive documentation will be provided to assist developers in using and extending the framework.

Stay tuned for future releases and enhancements.

---

## Contributing

We welcome contributions to **CorianderPHP**. If you'd like to contribute, please submit pull requests or report issues on GitHub.

---

## License

This project is licensed under the MIT License.
