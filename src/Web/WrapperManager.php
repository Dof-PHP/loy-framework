<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Facade\Annotation;
use Loy\Framework\Base\Exception\DuplicateWrapperDefinitionException;

final class WrapperManager
{
    const WRAPPER_DIR = ['Http', 'Wrapper'];

    private static $dirs = [];
    private static $wrappers = [
        'in'  => [],
        'out' => [],
        'err' => [],
    ];

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = ospath($item, self::WRAPPER_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Excetions may thrown but let invoker to catch for different scenarios
        //
        // use Loy\Framework\Base\Exception\InvalidAnnotationDirException;
        // use Loy\Framework\Base\Exception\InvalidAnnotationNamespaceException;
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, $ofProperties, $ofMethods) = $annotations;
                self::assembleWrappersFromAnnotations($ofClass, $ofMethods);
            }
        }, __CLASS__);
    }

    public static function assembleWrappersFromAnnotations(array $ofClass, array $ofMethods)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if ((! $namespace) || (! class_exists($namespace))) {
            return;
        }

        $namePrefix  = $ofClass['doc']['PREFIX'] ?? null;
        $typeDefault = $ofClass['doc']['TYPE']   ?? null;

        $ofMethods = $ofMethods['self'] ?? [];
        foreach ($ofMethods as $method => $_attrs) {
            $attrs = $_attrs['doc'] ?? [];
            $notwrapper = $attrs['NOTWRAPPER'] ?? false;
            if ($notwrapper) {
                continue;
            }
            $type = $attrs['TYPE'] ?? $typeDefault;
            if (! $type) {
                continue;
            }
            $name = $attrs['NAME'] ?? '';
            if ('_' === $name) {
                continue;
            }
            $name = $namePrefix ? join('.', [$namePrefix, $name]) : $name;
            if ($exists = (self::$wrappers[$type][$name] ?? false)) {
                $_class  = $exists['class']  ?? '?';
                $_method = $exists['method'] ?? '?';
                throw new DuplicateWrapperDefinitionException(
                    "{$name} => {$namespace}@{$method} ($_class@$_method)"
                );
            }
            self::$wrappers[$type][$name] = [
                'class'  => $namespace,
                'method' => $method,
            ];
        }
    }

    public static function hasWrapperErr(string $err) : bool
    {
        return !is_null(self::getWrapperErr($err));
    }

    public static function getWrapperErr(string $err = null) : ?array
    {
        $arr = self::$wrappers['err'];
        return $err ? ($arr[$err] ?? null) : $arr;
    }

    public static function hasWrapperOut(string $out) : bool
    {
        return !is_null(self::getWrapperOut($out));
    }

    public static function getWrapperOut(string $out = null) : ?array
    {
        $arr = self::$wrappers['out'];
        return $out ? ($arr[$out] ?? null) : $arr;
    }

    public static function hasWrapperIn(string $in) : bool
    {
        return !is_null(self::getWrapperIn($in));
    }

    public static function getWrapperIn(string $in = null) : ?array
    {
        $arr = self::$wrappers['in'];
        return $in ? ($arr[$in] ?? null) : $arr;
    }

    public static function getWrapperFinal(array $wrapper = null) : ?array
    {
        if (! $wrapper) {
            return null;
        }
        $class  = $wrapper['class']  ?? false;
        $method = $wrapper['method'] ?? false;
        if ((! $class) || (! $method) || (! class_exists($class) || (! method_exists($class, $method)))) {
            return null;
        }

        $result = (new $class)->{$method}();
        return is_array($result) ? $result : null;
    }

    public static function getWrappers()
    {
        return self::$wrappers;
    }
}
