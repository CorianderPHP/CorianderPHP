// Main TypeScript file for the home view.
// Note: If you make any changes to this file, remember to either build the project or run the watch task (e.g., 'php coriander nodejs run watch-ts').
// Without building or running the watcher, your changes will not be reflected in the generated 'index.js' file for the home view.

import { HomeDependency } from "./homeDependencies/HomeDependency";

const homeDependency = new HomeDependency();
homeDependency.logTheRandomNumber();
