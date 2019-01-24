<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Facade\Annotation;
use Loy\Framework\Base\Exception\DuplicatePipeDefinitionException;

final class PipeManager
{
    const PIPE_DIR = ['Http', 'Pipe'];

    private static $dirs  = [];
    private static $pipes = [];

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = ospath($item, self::PIPE_DIR);
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
                self::assemblePipesFromAnnotations($ofClass, $ofMethods);
            }
        }, __CLASS__);
    }

    public static function assemblePipesFromAnnotations(array $ofClass, array $ofMethods)
    {
        $name = $ofClass['doc']['NAME'] ?? null;
        if (! $name) {
            return;
        }

        $namespace = $ofClass['namespace'] ?? false;
        if ($namespace && class_exists($namespace)) {
            if ($exists = (self::$pipes[$name] ?? false)) {
                throw new DuplicatePipeDefinitionException(
                    "{$name} => {$namespace} ({$exists})"
                );
            }

            self::$pipes[$name] = $namespace;
        }
    }

    public static function getPipes()
    {
        return self::$pipes;
    }
}
