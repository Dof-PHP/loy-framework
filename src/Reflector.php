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

            list($type, $res) = self::formatClassMethod($method, $class);
            if ($res === false) {
                return [];
            }

            return [$type => $res];
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
        $type = ($namespace === $property->getDeclaringClass()->name) ? 'self' : 'parent';
        if ($type === 'parent') {
            return [$type, false];
        }

        $res = [];
        $res['doc'] = $property->getDocComment();
        $res['modifiers'] = Reflection::getModifierNames($property->getModifiers());

        return [$type, $res];
    }

    public static function formatClassMethod(ReflectionMethod $method, string $namespace) : array
    {
        $type = ($namespace === $method->getDeclaringClass()->name) ? 'self' : 'parent';
        // if (($type === 'parent') && ($method->getName() !== '__construct')) {
        // return [$type, false];
        // }

        $res = [];
        $res['doc'] = $method->getDocComment();
        $res['modifiers']  = Reflection::getModifierNames($method->getModifiers());
        $res['parameters'] = [];
        $parameters = $method->getParameters();
        foreach ($parameters as $parameter) {
            $res['parameters'][] = self::formatClassMethodParameter($parameter);
        }

        return [$type, $res];
    }

    public static function formatClassMethodParameter(ReflectionParameter $parameter) : array
    {
        $type = $parameter->hasType() ? $parameter->getType()->getName() : null;
        $builtin    = $type ? $parameter->getType()->isBuiltin() : null;
        $hasDefault = $parameter->isDefaultValueAvailable();
        $defaultVal = $hasDefault ? $parameter->getDefaultValue() : null;

        return [
            'name' => $parameter->getName(),
            'type' => [
                'type'    => $type,
                'builtin' => $builtin,
            ],
            'nullable' => $parameter->allowsNull(),
            'optional' => $parameter->isOptional(),
            'default'  => [
                'status' => $hasDefault,
                'value'  => $defaultVal,
            ]
        ];
    }
}
