<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Facade\Annotation;

class RepositoryManager
{
    const ORM_DIR = 'Repository';

    private static $dirs  = [];
    private static $repos = [];

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs  = [];
        self::$repos = [];

        array_map(function ($item) {
            $dir = join(DIRECTORY_SEPARATOR, [$item, self::ORM_DIR]);
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
        if ($exists = (self::$repos[$namespace] ?? false)) {
            $ns = $exists['namespace'] ?? '?';
            exception('DuplicateRepositoryNamespace', ['namespace' => $ns]);
        }

        self::$repos[$namespace]['meta'] = $ofClass['doc'] ?? [];
    }

    public static function get(string $namespace)
    {
        return self::$repos[$namespace] ?? null;
    }

    public static function getRepositories()
    {
        return self::$repos;
    }
}
