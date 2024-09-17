# CorianderPHP Framework

**CorianderPHP** is a lightweight, performance-focused PHP framework designed for building modern web applications. It includes support for vanilla PHP, TypeScript, and TailwindCSS, making it a powerful tool for developers who value flexibility and eco-design principles.

---

## Features

- **Vanilla PHP Framework**: Build applications using clean and optimized PHP code.
- **NodeJS Integration**: Preconfigured TypeScript and TailwindCSS support for modern frontend development.
- **CLI Tooling**: Includes a custom command-line interface (CLI) for generating views.
- **Performance**: Focuses on optimizing performance and reducing environmental impact through lean code and efficient asset handling.
  
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
   php coriander nodejs watch-ts    # To watch and compile TypeScript
   php coriander nodejs watch-tw    # To watch and compile TailwindCSS
   ```

---

## Command-Line Interface (CLI)

CorianderPHP provides a custom CLI tool to help manage views, controllers, and Node.js tasks:

### Creating Views

You can create a new view using the `make:view` command:
   ```bash
   php coriander make:view contactMe
   ```
This will generate a new view in `public/public_views/contact-me/` using the CorianderPHP templates for view and metadata.

### Creating Controllers

You can create a new controller using the `make:controller` command:
   ```bash
   php coriander make:controller contactMe
   ```
This will generate a new controller in `src/Controllers/ContactMeController.php` using the CorianderPHP templates. The controller will include methods like `index()` for the default page, `show($id)` for displaying specific items, and `store()` for handling form submissions.

### Creating Databases

You can create a new database configuration using the `make:database` command:
   ```bash
   php coriander make:database
   ```
This will prompt you to choose between MySQL and SQLite and generate the appropriate configuration file in the `config` folder.

---

## NodeJS Integration

**CorianderPHP** tightly integrates NodeJS, TypeScript, and TailwindCSS to provide a modern front-end development experience:

- **TypeScript**: Automatically compiles `.ts` files from the `nodejs/src` directory into JavaScript. Each page must have an `index.ts` file within its respective folder (e.g., `nodejs/src/home/index.ts`) to generate JavaScript specific to that page. You can also create additional utility or class files in other folders (e.g., `nodejs/src/Utils/Responsive.ts` or `nodejs/src/home/Search/Search.ts`) without needing an `index.ts`.

- **TailwindCSS**: Compiles CSS from `public/assets/css/input.css` and outputs a minified CSS file to `public/assets/css/output.css` for production.

### To manage these assets, the following commands are available:

#### Watch TypeScript
   ```bash
   php coriander nodejs watch-ts
   ```

#### Watch TailwindCSS
   ```bash
   php coriander nodejs watch-tw
   ```

#### Build for Production
   ```bash
   php coriander nodejs build-all
   ```

---

## Development Status

**CorianderPHP** is under active development. Some features, such as WebP image optimization and sitemap creation tools, are still being built. Additionally, detailed documentation will be provided in the near future to assist developers with using and extending the framework.

We encourage you to stay updated and check back for upcoming releases and improvements.

---

## Contributing

If you wish to contribute to **CorianderPHP**, please submit pull requests or report issues via GitHub.

---

## License

This project is licensed under the MIT License.