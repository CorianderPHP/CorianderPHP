# Custom Module Guide

Modules are reusable project services or packages. User-created modules should live in the app-owned `src/Modules` directory so framework updates never overwrite them.

Framework or official reusable modules can still live under `CorianderCore/modules`, but application code should not be added there.

## Project Modules

Create a project module under `src/Modules`:

```bash
mkdir -p src/Modules/ImageDataExtractor
```

Then create `src/Modules/ImageDataExtractor/Extractor.php`:

```php
<?php
declare(strict_types=1);

namespace App\Modules\ImageDataExtractor;

final class Extractor
{
    public function extract(string $path): array
    {
        return [];
    }
}
```

Use it from route handlers or middleware:

```php
use App\Modules\ImageDataExtractor\Extractor;

$metadata = (new Extractor())->extract($path);
```

The framework autoloader maps `App\` to `src/`.

## Framework Modules

`CorianderCore/modules` is reserved for framework-owned or official modules using the `CorianderCore\Modules\` namespace.

Do not place project-specific code there. The folder is inside the framework-managed `CorianderCore` tree and should be treated as core-owned.

## Best Practices

- Put application modules in `src/Modules`.
- Keep modules self-contained and reusable.
- Use descriptive namespaces and follow PSR-4 conventions.
- Keep framework-owned code under `CorianderCore`, and project-owned code under `src`.
- Document module APIs and include tests when the module contains important business logic.
