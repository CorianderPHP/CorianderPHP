# Contributor guide

CorianderPHP favors simple developer UX, predictable performance and few dependencies. Keep application code in `src` and framework code in `CorianderCore/core`. Use existing PHP/PSR facilities before introducing another abstraction or package.

## Comments and PHPDoc

Use native property, parameter and return types first. Add a short PHPDoc contract when a name and signature cannot explain a constraint. Describe array element types, units, callable contracts and meaningful exceptions. Do not repeat a native `string`, `bool` or `void` type without adding information.

```php
/**
 * Render a template with HTML-escaped string data.
 *
 * @param array<string,mixed> $data Variables exposed to the template.
 * @throws RuntimeException If the template is missing or outside the view root.
 */
public function render(string $name, array $data = []): string
```

This is an illustrative contract, not a new framework API. Use `list<string>` for sequential string lists, `array<string,mixed>` for named data, and array shapes such as `array{status:int,body:string}` for fixed structures. Collection/shape and callable annotations help IDEs and analyzers; they do not validate runtime input or require an analyzer dependency.

Use inline comments to explain decisions that a maintainer could otherwise accidentally undo: failing closed when cached middleware disappears, protecting renderer variables from extracted data, or reading one extra byte to detect overflow. Avoid step-by-step workflow blocks and comments such as “initialize the class” above a constructor call. Obvious methods need no docblock. Keep public contracts and migration guidance accurate when behavior changes.

## Validation and delivery

Run focused tests first and the full suite for behavior changes:

```bash
vendor/bin/phpunit --filter RelevantTest --testdox
vendor/bin/phpunit --testdox
```

Wait for test processes to finish, review staged changes and exclude temporary fixtures. Use exact tracked path casing on Windows too. Commit messages use gitmoji and end with a period.

When the user requests sequential fixes, create one issue/branch/PR per scoped fix from current `main`. Use `Closes #number`, wait for all relevant checks on the final commit, merge, delete the branch, update `main` and start the next. Update migration/release notes and include concrete documentation-agent references when public behavior changes.

Measure representative workloads before optimizing. Keep benchmark tooling optional, verify responses and compare equivalent environments. Report individual request latency and the scope of memory measurements; do not treat shared CI timing as a stable capacity limit. See [benchmarks](benchmarks.md), [the stabilization roadmap](0.3.0-stabilization.md) and the repository's [AGENTS.md](../AGENTS.md).
