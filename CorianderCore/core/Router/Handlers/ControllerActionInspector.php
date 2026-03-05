<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router\Handlers;

use ReflectionException;
use ReflectionMethod;

final class ControllerActionInspector
{
    public static function isPublicInvokable(object $controller, string $action): bool
    {
        if (!method_exists($controller, $action)) {
            return false;
        }

        try {
            $method = new ReflectionMethod($controller, $action);
        } catch (ReflectionException) {
            return false;
        }

        return $method->isPublic() && !$method->isStatic();
    }
}
