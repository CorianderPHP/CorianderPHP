<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class HttpMethods
{
    public readonly array $methods;

    public function __construct(string ...$methods)
    {
        if ($methods === []) {
            throw new \InvalidArgumentException('Declare at least one HTTP method.');
        }
        $normalized = array_map(static fn(string $method): string => strtoupper(trim($method)), $methods);
        foreach ($normalized as $method) {
            if (preg_match('/^[A-Z]+$/', $method) !== 1) {
                throw new \InvalidArgumentException('Invalid HTTP method: ' . $method);
            }
        }
        if (in_array('GET', $normalized, true)) {
            $normalized[] = 'HEAD';
        }
        $this->methods = array_values(array_unique($normalized));
    }
}
