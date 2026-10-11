# Page scripts

Node is optional. Use PHP alone, or build TypeScript during development/CI and deploy the resulting public assets. PHP never invokes Node, executes TypeScript or scans the source tree to select scripts.

## Files beside pages

Keep build tools, npm dependencies and configuration in `nodejs`. Put browser entries beside their GET page:

```text
src/
  Views/
    _script.ts
    index.get.php
    index.ts
    about/
      index.get.php
      index.ts
    users/
      _script.ts
      [id].get.php
      [id].post.php
      [id].ts
    teams/[team]/users/[id]/index.get.php
    teams/[team]/users/[id]/index.ts
  Scripts/
    menu.ts
    http.ts
    types.ts
```

`index.ts` pairs with `index.get.php`; `[id].ts` pairs with `[id].get.php`. POST-only files do not create browser entries. Other TypeScript files are ordinary imported modules. Private names/directories beginning with `_` or `.` are excluded from entry discovery, except directory `_script.ts`. Discovery reads filenames without executing PHP metadata or pages.

Imports shared by directory and page entries use generated ESM chunks under `public/assets/js/pages/_chunks`. A shared module initializes once and keeps the same state across entries on one page. Deploy the entire generated pages directory, including chunks; keep initialization calls explicit when each entry needs its own behavior.

Output mirrors the tree under `public/assets/js/pages`. A request for `/users/42` uses `users/[id].js`, with literal brackets URL-encoded in its tag. All IDs share that asset; builds do not generate a file per user.

## Directory inheritance

A full rendered GET page loads existing compiled directory `_script.js` files root to leaf, then its page bundle. Each selected bundle appears once, after page HTML and before the footer. Missing files are omitted. Headers and footers still inherit independently.

For `users/[id].get.php`, the possible order is:

1. `pages/_script.js`
2. `pages/users/_script.js`
3. `pages/users/[id].js`

Tags use `type="module"`, respect `PUBLIC_URL_PREFIX` and append a content fingerprint as a cache version. Generated scripts carry this fingerprint in a leading comment, so PHP only reads a bounded prefix. Existing unmarked scripts use a SHA-256 content-hash fallback; rebuild them to use the fast path. Content changes invalidate URLs even when timestamps are preserved. Modules run after HTML parsing. Directory script exports do not become implicit globals; import dependencies explicitly. Tag order is root to leaf, but asynchronous work should coordinate through promises/imports.

Returned handler responses retain their content: JSON, redirects and explicit HTML are not decorated. HEAD emits no body. Explicit unsuffixed templates receive ancestor scripts when rendering with layouts; `Responses::view(..., layout: false)` produces a fragment without automatic scripts.

Custom `ViewRenderer` roots skip automatic assets unless their matching compiled pages directory is supplied as the second constructor argument. Its URLs still use `assets/js/pages`. Default `src/Views` needs no configuration. Prebuilt deployments need neither Node nor TypeScript sources.

## Share ordinary modules

`menu.ts`, `http.ts` and `types.ts` are illustrative application modules. Create only helpers your application needs; the framework supplies no placeholder APIs.

```typescript
// src/Scripts/types.ts
export type MenuOptions = { selector: string };
```

```typescript
// src/Scripts/menu.ts
import type { MenuOptions } from './types';

export function initializeMenu(options: MenuOptions): void {
    document.querySelector<HTMLButtonElement>(options.selector)
        ?.addEventListener('click', event => {
            const button = event.currentTarget as HTMLButtonElement;
            button.setAttribute('aria-expanded',
                String(button.getAttribute('aria-expanded') !== 'true'));
        });
}
```

```typescript
// src/Views/about/index.ts
import { initializeMenu } from '../../Scripts/menu';
import type { MenuOptions } from '../../Scripts/types';

const options: MenuOptions = { selector: '[data-menu-toggle]' };
initializeMenu(options);
```

Another view imports the same helper normally. Type-only imports emit no browser code. Helpers do not produce standalone entries or require metadata registration. Each entry bundles its imports; put site-wide initialization in the root `_script.ts` and call page-specific behavior from page entries.

Every TypeScript file has its own module scope, including plain entries without imports. Different pages can reuse local names such as `const button` without adding `export {}`. Shared values/types need explicit exports and imports; intentional ambient declarations should use TypeScript's `declare global` inside a module.

## Build and watch

From the project root:

```bash
php coriander nodejs install
php coriander nodejs run watch-all
php coriander nodejs run build-prod
```

Builds check types with `tsc --noEmit`, then use esbuild to write modules. Watch tracks Views, Scripts and TypeScript configuration, including entry additions, directory renames, removals and shared imports. Errors preserve last good bundles; saving a fix resumes builds.

Successful builds keep a local generated-file list. Cleanup removes only recorded obsolete bundles and preserves manual assets. Keep that list between builds; it is ignored by Git and unnecessary in production. Older unrecorded output requires manual cleanup.

Tailwind scans Views and Scripts through explicit CSS `@source` declarations. Add a source path for other application class locations. See [Node tooling](nodejs.md), [migration](migration-0.4.0.md) and [measurements](benchmarks.md).
