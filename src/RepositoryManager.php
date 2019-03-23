<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;

final class RepositoryManager
{
    const REPOSITORY_DIR = 'Repository';

    private static $dirs = [];
    private static $repositories = [];

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs = [];
        self::$repositories = [];

        array_map(function ($item) {
            $dir = ospath($item, self::REPOSITORY_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Exceptions may thrown but let invoker to catch for different scenarios
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, , ) = $annotations;
                self::assemble($ofClass);
            }
        }, __CLASS__);
    }

    /**
     * Assemble Repository From Annotations
     */
    public static function assemble(array $ofClass)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if ($exists = (self::$repositories[$namespace] ?? false)) {
            exception('DuplicateRepositoryInterface', compact(['exists']));
        }

        self::$repositories[$namespace] = $ofClass['doc'] ?? [];
    }

    public static function __annotationFilterImplementor(string $storage) : string
    {
        if (! class_exists($storage)) {
            exception('StorageNotExists', ['namespace' => $storage]);
        }

        return $storage;
    }

    public static function get(string $namespace)
    {
        return self::$repositories[$namespace] ?? null;
    }

    public static function getRepositories()
    {
        return self::$repositories;
    }
}
