<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Facade\Annotation;

final class DataModelManager
{
    const MODEL_DIR = 'DM';

    private static $dirs = [];
    private static $models = [];

    public static function load(array $dirs)
    {
        $cache = Kernel::formatCacheFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$models) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_MODEL_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([self::$dirs, self::$models], $cache);
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCacheFile(__CLASS__);
        if (is_file($cache)) {
            unlink($cache);
        }
    }

    public static function compile(array $dirs, bool $cache = false)
    {
        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs = [];
        self::$models = [];

        array_map(function ($item) {
            $dir = ospath($item, self::MODEL_DIR);
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
            array2code([self::$dirs, self::$models], Kernel::formatCacheFile(__CLASS__));
        }
    }

    public static function assemble(array $ofClass, array $ofProperties)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if ($exists = (self::$models[$namespace] ?? false)) {
            exception('DuplicateDataModelNamespace', ['namespace' => $namespace]);
        }
        if (! ($ofClass['doc']['TITLE'] ?? false)) {
            exception('MissingModelTitle', ['model' => $namespace]);
        }

        self::$models[$namespace]['meta'] = $ofClass['doc'] ?? [];

        foreach ($ofProperties as $name => $attrs) {
            if (! ($attrs['doc']['TITLE'] ?? false)) {
                exception('MissingDataMODELAttrTitle', ['model' => $namespace, 'attr' => $name]);
            }
            if (! ($attrs['doc']['TYPE'] ?? false)) {
                exception('MissingDataModelAttrType', ['model' => $namespace, 'attr' => $name]);
            }

            self::$models[$namespace]['properties'][$name] = $attrs['doc'] ?? [];
        }
    }

    public static function get(string $namespace)
    {
        return self::$models[$namespace] ?? null;
    }

    public static function getDirs()
    {
        return self::$dirs;
    }

    public static function getModels()
    {
        return self::$models;
    }
}
