<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\Annotation;

class ORMManager
{
    const ORM_DIR = 'ORM';
    const REGEX = '#@([a-zA-z]+)\((.*)\)#';

    private static $dirs = [];
    private static $orms = [];

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs = [];
        self::$orms = [];

        array_map(function ($item) {
            $dir = join(DIRECTORY_SEPARATOR, [$item, self::ORM_DIR]);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);


        // Excetions may thrown but let invoker to catch for different scenarios
        //
        // use Loy\Framework\Base\Exception\InvalidAnnotationDirException;
        // use Loy\Framework\Base\Exception\InvalidAnnotationNamespaceException;
        Annotation::parseClassDirs(self::$dirs, self::REGEX, function ($annotations) {
            if ($annotations) {
                list($ofClass, $ofProperties, $ofMethods) = $annotations;
                self::assembleOrmsFromAnnotations($ofClass, $ofProperties);
            }
        }, __CLASS__);
    }

    public static function assembleOrmsFromAnnotations(array $ofClass, array $ofProperties)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if ($exists = (self::$orms[$namespace] ?? false)) {
            $ns = $exists['namespace'] ?? '?';
            throw new \Exception('DuplicateOrmNamespaceException: '.$ns);
        }
        self::$orms[$namespace]['meta'] = $ofClass['doc'] ?? [];

        foreach ($ofProperties as $name => $attrs) {
            $column = $attrs['doc']['COLUMN'] ?? false;
            if (! $column) {
                continue;
            }
            $_exists = self::$orms[$namespace]['columns'][$column] ?? false;
            if ($_exists) {
                throw new \Exception(
                    'DuplicateOrmColumnException: '."{$namespace} ({$column} <=> {$_exists} & {$name})"
                );
            }
            self::$orms[$namespace]['properties'][$name] = $attrs['doc'] ?? [];
            self::$orms[$namespace]['columns'][$column]  = $name;
        }
    }

    public static function getOrm(string $namespace)
    {
        return self::$orms[$namespace] ?? null;
    }

    public static function getOrms()
    {
        return self::$orms;
    }
}
