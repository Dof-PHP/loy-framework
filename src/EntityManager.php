<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Facade\Annotation;
use Dof\Framework\DDD\Repository;

final class EntityManager
{
    const ENTITY_DIR = 'Entity';

    private static $dirs = [];
    private static $entities = [];

    public static function compile(array $dirs)
    {
        $cache = Kernel::formatCacheFile(__FILE__);
        if (file_exists($cache)) {
            list(self::$dirs, self::$entities) = load_php($cache);
            return;
        }

        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs = [];
        self::$entities = [];

        array_map(function ($item) {
            $dir = ospath($item, self::ENTITY_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Exceptions may thrown but let invoker to catch for different scenarios
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, $ofProperties, ) = $annotations;
                self::assemble($ofClass, $ofProperties);
            }
        }, __CLASS__);

        array2code([self::$dirs, self::$entities], $cache);
    }

    /**
     * Assemble Orms From Annotations
     */
    public static function assemble(array $ofClass, array $ofProperties)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if ($exists = (self::$orms[$namespace] ?? false)) {
            exception('DuplicateOrmNamespace', ['namespace' => $namespace]);
        }
        self::$entities[$namespace]['meta'] = $ofClass['doc'] ?? [];

        foreach ($ofProperties as $name => $attrs) {
            self::$entities[$namespace]['properties'][$name] = $attrs['doc'] ?? [];
        }
    }

    public static function __annotationMultipleMergeArgument()
    {
        return 'kv';
    }

    public static function __annotationMultipleArgument() : bool
    {
        return true;
    }

    public static function __annotationFilterArgument(string $arguments, array $argvs) : array
    {
        return array_trim_from_string($arguments, ',');
    }

    public static function __annotationFilterRepository(string $repository) : string
    {
        if (! interface_exists($repository)) {
            exception('RepositoryNotExists', compact('repository'));
        }
        if (! is_subclass_of($repository, Repository::class)) {
            exception('InvalidRepositoryInterface', compact('repository'));
        }

        return trim($repository);
    }

    public static function get(string $namespace)
    {
        return self::$entities[$namespace] ?? null;
    }

    public static function getEntities()
    {
        return self::$entities;
    }
}
