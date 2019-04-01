<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;
use Loy\Framework\DDD\Repository;

final class EntityManager
{
    const ENTITY_DIR = 'Entity';

    private static $dirs = [];
    private static $entities = [];

    public static function compile(array $dirs)
    {
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

        foreach ($ofProperties['self'] ?? [] as $name => $attrs) {
            self::$entities[$namespace]['properties'][$name] = $attrs['doc'] ?? [];
        }
    }

    public static function __annotationFilterAssembler(string $assembler) : string
    {
        if (! class_exists($assembler)) {
            exception('AssemblerNotExists', compact('assembler'));
        }

        return trim($assembler);
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
