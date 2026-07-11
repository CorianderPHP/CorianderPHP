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
- `docs/` contains framework documentation.
- `.github/workflows/` contains CI and release automation.

## Development

- Keep changes scoped to the requested behavior.
- Add or update tests for behavior changes.
- Update documentation and `readme.md` when developer UX changes.
- Run the focused test suite first, then the full suite when behavior changes.

```bash
vendor/bin/phpunit --testdox
```

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

- Before preparing a release, compare `CorianderCore/VERSION` with the latest
  release tag.
- If `CorianderCore/VERSION` is equal to the latest release tag, bump the
  framework version.
- The default bump is a patch increment: `+0.0.1`.

## Documentation Repository

Framework releases dispatch documentation updates to `CorianderPHP/Documentation`
through the `framework-released` repository dispatch event.
