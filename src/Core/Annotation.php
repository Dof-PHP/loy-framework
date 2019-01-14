<?php

declare(strict_types=1);

namespace Loy\Framework\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use Loy\Framework\Core\Exception\InvalidAnnotationDirException;
use Loy\Framework\Core\Exception\InvalidAnnotationNamespaceException;

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
            if (! is_dir($dir)) {
                throw new InvalidAnnotationDirException($routeDir);
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

    public static function parseNamespace(string $namespace, string $regex = null, string $origin = null) : array
    {
        try {
            $reflector = new ReflectionClass($namespace);
        } catch (ReflectionException $e) {
            return [];
        }

        $classDocComment = $reflector->getDocComment();
        $ofClass = [];
        if (false !== $classDocComment) {
            $ofClass = self::parseComment($classDocComment, $regex, $origin);
        }
        $ofClass['namespace'] = $namespace;

        $ofMethods = [];
        $methods   = $reflector->getMethods();
        foreach ($methods as $method) {
            $comment = $method->getDocComment();
            if (false !== $comment) {
                $ofMethods[$method->name] = self::parseComment($comment, $regex, $origin);
            }
        }

        return [
            $ofClass,
            $ofMethods,
        ];
    }

    public static function parseComment(string $comment, string $regex = null, string $origin = null) : array
    {
        if (! $comment) {
            return [];
        }

        $regex = $regex ?: self::DEFAULT_REGEX;
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
