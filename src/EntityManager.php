<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;

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

        $ofProperties = $ofProperties['self'] ?? [];
        foreach ($ofProperties as $name => $attrs) {
            self::$entities[$namespace]['properties'][$name] = $attrs['doc'] ?? [];
        }
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
