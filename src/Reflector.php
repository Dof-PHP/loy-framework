<?php

declare(strict_types=1);

namespace Loy\Framework;

use Reflection;
use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionException;

final class Reflector
{
    public static function getClassMethod(string $class, string $method) : array
    {
        if ((! class_exists($class)) || (! method_exists($class, $method))) {
            return [];
        }

        try {
            $_class = new ReflectionClass($class);
            $method = $_class->getMethod($method);

            return self::formatClassMethod($method, $class);
        } catch (ReflectionException $e) {
            return [];
        }
    }

    public static function getClassConstructor(string $namespace) : array
    {
        return self::getClassMethod($namespace, '__construct');
    }

    public static function formatClassProperty(ReflectionProperty $property, string $namespace) : array
    {
        return [
            'declar'  => $namespace,
            'reflect' => $property->getDeclaringClass()->name,
            'doc' => $property->getDocComment(),
            'modifiers' => Reflection::getModifierNames($property->getModifiers()),
        ];
    }

    public static function formatClassMethod(ReflectionMethod $method, string $namespace) : array
    {
        $res = [
            'declar'  => $namespace,
            'reflect' => $method->getDeclaringClass()->name,
            'doc' => $method->getDocComment(),
            'modifiers' => Reflection::getModifierNames($method->getModifiers()),
        ];

        $parameters = $method->getParameters();
        foreach ($parameters as $parameter) {
            $res['parameters'][] = self::formatClassMethodParameter($parameter);
        }

        return $res;
    }

    public static function formatClassMethodParameter(ReflectionParameter $parameter) : array
    {
        $type = $parameter->hasType() ? $parameter->getType()->getName() : null;
        $builtin    = $type ? $parameter->getType()->isBuiltin() : null;
        $hasDefault = $parameter->isDefaultValueAvailable();
        $defaultVal = $hasDefault ? $parameter->getDefaultValue() : null;

        return [
            'name' => $parameter->getName(),
            'type' => $type,
            'builtin'  => $builtin,
            'nullable' => $parameter->allowsNull(),
            'optional' => $parameter->isOptional(),
            'default'  => [
                'status' => $hasDefault,
                'value'  => $defaultVal,
            ]
        ];
    }
}
