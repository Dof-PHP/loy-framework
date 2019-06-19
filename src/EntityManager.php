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

    /**
     * Load domain entities among domain directories with cache management
     *
     * @param array $dirs: Domain directories
     */
    public static function load(array $dirs)
    {
        $cache = Kernel::formatCacheFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$entities) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_ENTITY_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([self::$dirs, self::$entities], $cache);
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCacheFile(__CLASS__);
        if (is_file($cache)) {
            unlink($cache);
        }
    }

    /**
     * Load domain entities among domain directories
     *
     * @param array $dirs: Domain directories
     */
    public static function compile(array $dirs, bool $cache = false)
    {
        // Reset
        self::$dirs = [];
        self::$entities = [];

        if (count($dirs) < 1) {
            return;
        }

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

        if ($cache) {
            array2code([self::$dirs, self::$entities], Kernel::formatCacheFile(__CLASS__));
        }
    }

    /**
     * Assemble entities From Annotations
     */
    public static function assemble(array $ofClass, array $ofProperties)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if ($exists = (self::$entities[$namespace] ?? false)) {
            exception('DuplicateEntityNamespace', ['namespace' => $namespace]);
        }
        if (! ($ofClass['doc']['TITLE'] ?? false)) {
            exception('MissingEntityTitle', ['entity' => $namespace]);
        }

        self::$entities[$namespace]['meta'] = $ofClass['doc'] ?? [];

        foreach ($ofProperties as $name => $attrs) {
            if ($attrs['doc']['ANNOTATION'] ?? true) {
                if (! ($attrs['doc']['TITLE'] ?? false)) {
                    exception('MissingEntityAttrTitle', ['entity' => $namespace, 'attr' => $name]);
                }
                if (! ($attrs['doc']['TYPE'] ?? false)) {
                    exception('MissingEntityAttrType', ['entity' => $namespace, 'attr' => $name]);
                }

                self::$entities[$namespace]['properties'][$name] = $attrs['doc'] ?? [];
            }
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

    public static function getDirs()
    {
        return self::$dirs;
    }

    public static function getEntities()
    {
        return self::$entities;
    }
}
