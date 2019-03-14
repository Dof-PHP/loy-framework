<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;

final class EntityManager
{
    const ENTITY_DIR = 'Entity';

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
            $ns = $exists['namespace'] ?? '?';
            exception('DuplicateOrmNamespace', ['namespace' => $ns]);
        }
        self::$orms[$namespace]['meta'] = $ofClass['doc'] ?? [];

        $ofProperties = $ofProperties['self'] ?? [];
        foreach ($ofProperties as $name => $attrs) {
            $column = $attrs['doc']['COLUMN'] ?? false;
            if (! $column) {
                continue;
            }
            $_exists = self::$orms[$namespace]['columns'][$column] ?? false;
            if ($_exists) {
                exception('DuplicateOrmColumn', [
                    'namespace' => $namespace,
                    'column'    => $column,
                    'exists'    => $_esists,
                    'name'      => $name,
                ]);
            }
            self::$orms[$namespace]['properties'][$name] = $attrs['doc'] ?? [];
            self::$orms[$namespace]['columns'][$column]  = $name;
        }
    }

    public static function get(string $namespace)
    {
        return self::$orms[$namespace] ?? null;
    }

    public static function getOrms()
    {
        return self::$orms;
    }
}
