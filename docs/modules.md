# Custom Module Guide

Modules extend the framework with reusable packages. They live under `CorianderCore/modules` and are autoloaded via the `CorianderCore\\Modules` namespace defined in Composer:

```php
'CorianderCore\\Modules\\' => PROJECT_ROOT . '/CorianderCore/Modules/',
```

## Example: ImageDataExtractor Module

Create a PHP class inside the modules directory:

```bash
mkdir -p CorianderCore/Modules/ImageDataExtractor
cat <<'PHP' > CorianderCore/Modules/ImageDataExtractor/Extractor.php
<?php
namespace CorianderCore\\Modules\\ImageDataExtractor;

class Extractor
{
    public function extract(string $path): array
    {
        // extraction logic returning metadata
        return [];
    }
}
PHP
```

The framework's autoloader discovers modules automatically, so the `CorianderCore\\Modules\\ImageDataExtractor\\Extractor` class is available immediately across your project.

## Best Practices

- Keep modules self-contained; they should not depend on application-specific code.
- Use descriptive namespaces and follow PSR-4 conventions.
- Document module APIs and include tests to ease reuse.

