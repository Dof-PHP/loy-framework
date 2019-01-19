<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Closure;
use Reflection;
use ReflectionClass;
use ReflectionException;
// use ReflectionMethod;
// use ReflectionProperty;
use Loy\Framework\Base\Exception\InvalidAnnotationDirException;
use Loy\Framework\Base\Exception\InvalidAnnotationNamespaceException;

class Annotation
{
    const DEFAULT_REGEX = '#@([a-zA-z]+)\((.*)\)#';

    public static function parseClassDirs(
        array $dirs,
        string $regex = null,
        Closure $callback = null,
        string $origin = null
    ) {
        foreach ($dirs as $dir) {
            if ((! is_string($dir)) || (! is_dir($dir))) {
                throw new InvalidAnnotationDirException(stringify($dir));
            }

            self::parseClassDir($dir, $regex, $callback, $origin);
        }
    }

    public static function parseClassDir(
        string $dir,
        string $regex = null,
        Closure $callback = null,
        string $origin = null
    ) {
        $regex  = $regex ?: self::DEFAULT_REGEX;
        $result = [];

        walk_dir($dir, function ($path) use ($callback, $regex, $origin, &$result) {
            $realpath = $path->getRealpath();
            if ($path->isFile() && ('php' === $path->getExtension())) {
                $result[$realpath] = self::parseClassFile($realpath, $regex, $callback, $origin);
                return;
            }
            if ($path->isDir()) {
                self::parseClassDir($realpath, $regex, $callback, $origin);
                return;
            }
        });

        return $result;
    }

    public static function parseClassFile(
        string $path,
        string $regex = null,
        Closure $callback = null,
        string $origin = null
    ) {
        $ns = get_namespace_of_file($path, true);
        if (! class_exists($ns)) {
            throw new InvalidAnnotationNamespaceException($ns);
        }

        $annotations = self::parseNamespace($ns, $regex, $origin);

        if ($callback) {
            $callback($annotations);
        }

        return $annotations;
    }

    public static function parseNamespace(
        string $namespace,
        string $regex = null,
        string $origin = null
    ) : array {
        try {
            $reflector = new ReflectionClass($namespace);
        } catch (ReflectionException $e) {
            return [];
        }

        $classDocComment = $reflector->getDocComment();
        $ofClass = [];
        if (false !== $classDocComment) {
            $ofClass['doc'] = self::parseComment($classDocComment, $regex, $origin);
        }
        $ofClass['namespace'] = $namespace;
        $ofProperties = self::parseProperties($namespace, $regex, $origin, $reflector->getProperties());
        $ofMethods = self::parseMethods($namespace, $regex, $origin, $reflector->getMethods());

        return [
            $ofClass,
            $ofProperties,
            $ofMethods,
        ];
    }

    public static function parseProperties(
        string $namespace,
        string $regex,
        string $origin,
        array $properties = null
    ) : array {
        if (! $properties) {
            return [];
        }

        $res = [];
        foreach ($properties as $property) {
            $res[$property->name]['meta']['modifiers'] = Reflection::getModifierNames(
                $property->getModifiers()
            );
            $comment = $property->getDocComment();
            if ($comment) {
                $res[$property->name]['doc'] = self::parseComment($comment, $regex, $origin);
            }
        }

        return $res;
    }

    public static function parseMethods(
        string $namespace,
        string $regex,
        string $origin,
        array $methods = null
    ) : array {
        if (! $methods) {
            return [];
        }

        $res = [];
        foreach ($methods as $method) {
            $mfile = $method->getFileName();
            if (! $mfile) {
                continue;
            }
            $nsMethod = get_namespace_of_file($mfile, true);
            if ($namespace !== $nsMethod) {
                continue;
            }
            $comment = $method->getDocComment();
            if (false !== $comment) {
                $res[$method->name]['doc'] = self::parseComment($comment, $regex, $origin);
            }
            $res[$method->name]['meta']['modifiers'] = Reflection::getModifierNames(
                $method->getModifiers()
            );
            $parameters = $method->getParameters();
            if (! $parameters) {
                $res[$method->name]['parameters'] = [];
                continue;
            }
            foreach ($parameters as $parameter) {
                $type    = $parameter->hasType() ? $parameter->getType()->getName() : null;
                $builtin = $type ? $parameter->getType()->isBuiltin() : null;
                $hasDefault = $parameter->isDefaultValueAvailable();
                $defaultVal = $hasDefault ? $parameter->getDefaultValue() : null;
                $res[$method->name]['parameters'][] = [
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

        return $res;
    }

    public static function parseComment(string $comment, string $regex = null, string $origin = null) : array
    {
        if (! $comment) {
            return [];
        }

        $regex = $regex ?: self::DEFAULT_REGEX;
        $res = [];
        $arr = explode(PHP_EOL, $comment);
        foreach ($arr as $line) {
            $matches = [];
            if (1 === preg_match($regex, $line, $matches)) {
                $key = $matches[1] ?? false;
                $val = $matches[2] ?? false;
                if ((! $key) || (! $val)) {
                    continue;
                }
                if (is_null($origin)) {
                    $val = trim($val);
                    if ($val && (mb_strpos($val, ',') !== false)) {
                        $val = array_trim(explode(',', $val));
                    }
                } else {
                    $callback = 'filterAnnotation'.ucfirst(strtolower($key));
                    if (method_exists($origin, $callback)) {
                        $val = call_user_func_array([$origin, $callback], [$val]);
                        // $val = is_object($origin)
                        // ? $origin->{$callback}($val)
                        // : $origin::$callback($val);
                    }
                }

                $res[strtoupper($key)] = $val;
            }
        }

        return $res;
    }
}
