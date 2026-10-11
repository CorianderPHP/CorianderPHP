# AGENTS.md

## Project

CorianderPHP is a lightweight PHP framework focused on simple developer UX,
performance, and a low dependency count.

## Principles

- Keep the framework lightweight and maintainable.
- Prefer simple, explicit APIs over large abstractions.
- Follow SOLID and DRY when they improve clarity and reduce real duplication.
- Do not add dependencies unless there is a strong reason.
- Keep project-owned application code outside `CorianderCore`.

## Structure

- `CorianderCore/` contains framework-owned code.
- `src/` is the app-owned structure for projects using the framework.
- `src/Views/` is the single private tree for method pages and HTTP handlers.
- `src/Scripts/` contains app-owned TypeScript helpers shared through ordinary imports.
- `nodejs/` holds optional asset tooling; compiled page modules mirror Views in `public/assets/js/pages/`.
- `public/` contains the front controller and assets; keep application PHP outside it.
- `docs/` contains framework documentation.
- `.github/workflows/` contains CI and release automation.

## Development

- Direct `.get.php` pages render PHP/HTML once; other methods return request-to-response callables.
- Preserve inherited `_middleware.php`, independent `_header.php`/`_footer.php`,
  and optional shallow `_metadata.php` defaults. Keep request values raw and
  escape metadata/view strings once for HTML. See `docs/0.4.0-design.md`.
- Reuse route discovery validation for generators and keep discovery free of
  application execution. No deployment build command is required.
- Pair optional page scripts with GET files; directory `_script.ts` entries accumulate
  root to leaf. Keep PHP script selection limited to current ancestor asset paths.
  Do not compile or scan TypeScript in requests. See `docs/page-scripts.md`.
- Use ordinary imports for shared scripts and types; no metadata registration.
  Preserve forced module detection so plain entries keep independent scopes.
  Preserve ESM splitting so shared imports retain module identity across entries;
  deploy generated chunks with entries and include them in asset byte measurements.
  Keep script cache versions content-based; generated fingerprints belong in asset
  prefixes so PHP reads stay bounded. Preserve hashing fallback for unmarked assets.
  Preserve manual JS and last good builds; cleanup owns only recorded generated files.
- Run `npm test` and relevant asset builds from `nodejs/` when changing tooling.
  Keep Tailwind source declarations aligned with Views and Scripts.

- Keep changes scoped to the requested behavior.
- Add or update tests for behavior changes.
- Update documentation and `readme.md` when developer UX changes.
- Run the focused test suite first, then the full suite when behavior changes.
- Wait for every test process to finish before staging files.
- Stage explicit paths and review the staged diff; keep temporary fixtures out
  of commits. Respect tracked path casing, including `CorianderCore/core/`.

```bash
vendor/bin/phpunit --testdox
```

## Comments and PHPDoc

- Prefer native parameter, return and property types.
- Use PHPDoc when it adds information: collection element types, array shapes,
  callable signatures, meaningful exceptions, units or public API constraints.
- Give public APIs a short contract when their name and types are insufficient.
  Do not require a docblock for every obvious method.
- Explain why a safety or performance decision exists; do not narrate each line,
  repeat native types, or maintain long implementation workflow lists.
- Keep comments accurate when behavior changes. See `docs/contributing.md`.

## Performance

- Measure representative behavior before adding optimizations or abstractions.
- Keep optional benchmark tooling separate from runtime dependencies.
- Verify benchmark responses and record environment, load, individual request
  latency percentiles and memory measurement limits.
- Compare equivalent scenarios; shared CI timing is informational, not an
  absolute performance gate. See `docs/benchmarks.md`.

## Issue and PR Workflow

When the user authorizes the sequential issue/PR workflow:

- Create one issue and branch per scoped fix, from the latest `main`.
- Reference the issue with `Closes #<number>` in the PR.
- Merge only after all relevant checks on the latest PR commit pass.
- Delete the merged local and remote branch, update `main`, then start the next
  fix so it includes previous fixes.
- Prepare release notes with changes; publish a release only when requested.

## Commits

- Commit messages must use gitmoji.
- Commit messages must end with a period.

Example:

```text
🐛 Fix updater release archive downloads.
```

## Release Notes

- Mention breaking changes clearly.
- Include compatibility and upgrade guidance.
- Keep release notes focused on user-visible behavior and developer UX.

## Versioning

- If an agent notices that `CorianderCore/VERSION` is equal to the latest
  release tag, bump the framework version immediately.
- Do not wait for release preparation; forgetting the version bump can create a
  release with the wrong tag/version.
- The default bump is a patch increment: `+0.0.1`.

## Documentation Repository

Framework releases dispatch documentation updates to `CorianderPHP/Documentation`
through the `framework-released` repository dispatch event.

When preparing framework changes that affect documentation, include useful
references for the documentation agent in release notes or PR descriptions:

- changed file paths
- changed public methods, functions, commands, or configuration keys
- behavior changes that require guide updates
- migration steps or compatibility notes
