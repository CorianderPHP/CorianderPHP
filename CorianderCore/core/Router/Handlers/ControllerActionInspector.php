<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router\Handlers;

use ReflectionException;
use ReflectionMethod;
use CorianderCore\Core\Router\HttpMethods;

final class ControllerActionInspector
{
    public static function allowedMethods(object $controller, string $action): array
    {
        $attributes = (new ReflectionMethod($controller, $action))->getAttributes(HttpMethods::class);
        if ($attributes !== []) {
            return $attributes[0]->newInstance()->methods;
        }

        return match (strtolower($action)) {
            'store' => ['POST'],
            'update' => ['POST', 'PUT', 'PATCH'],
            'delete', 'destroy' => ['POST', 'DELETE'],
            default => ['GET', 'HEAD'],
        };
    }

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
