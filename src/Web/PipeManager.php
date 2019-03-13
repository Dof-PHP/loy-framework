<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Facade\Annotation;

final class PipeManager
{
    const PIPE_DIR = ['Http', 'Pipe'];
    const PIPE_HANDLER = 'through';

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

        // Exceptions may thrown but let invoker to catch for different scenarios
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, , $ofMethods) = $annotations;
                self::assemble($ofClass, $ofMethods);
            }
        }, __CLASS__);
    }

    /**
     * Assemble Pipes From Annotations
     */
    public static function assemble(array $ofClass, array $ofMethods)
    {
        $name = $ofClass['doc']['NAME'] ?? null;
        if (! $name) {
            return;
        }

        $namespace = $ofClass['namespace'] ?? false;
        if ($namespace && class_exists($namespace)) {
            if ($exists = (self::$pipes[$name] ?? false)) {
                exception('DuplicatePipeDefinition', [
                    'name'   => $name,
                    'class'  => $namespace,
                    'exists' => $exists,
                ]);
            }

            self::$pipes[$name] = $namespace;
        }
    }

    public static function getPipes()
    {
        return self::$pipes;
    }
}
