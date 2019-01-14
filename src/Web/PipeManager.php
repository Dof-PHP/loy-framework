<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Core\Annotation;
use Loy\Framework\Core\Exception\InvalidAnnotationDirException;
use Loy\Framework\Core\Exception\InvalidAnnotationNamespaceException;
use Loy\Framework\Web\Exception\InvalidPipeDirException;
use Loy\Framework\Web\Exception\InvalidHttpPipeNamespaceException;
use Loy\Framework\Web\Exception\DuplicatePipeDefinitionException;

final class PipeManager
{
    const PIPE_DIR = 'Http/Pipe';
    const REGEX = '#@([a-zA-z]+)\((.*)\)#';

    private static $dirs  = [];
    private static $pipes = [];

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        self::$dirs = array_map(function ($item) {
            return join(DIRECTORY_SEPARATOR, [$item, self::PIPE_DIR]);
        }, $dirs);

        try {
            Annotation::parseClassDirs(self::$dirs, self::REGEX, function ($annotations) {
                if ($annotations) {
                    list($ofClass, $ofMethods) = $annotations;
                    self::assemblePipesFromAnnotations($ofClass, $ofMethods);
                }
            }, __CLASS__);
        } catch (InvalidAnnotationDirException $e) {
            throw new InvalidPipeDirException($e->getMessage());
        } catch (InvalidAnnotationNamespaceException $e) {
            throw new InvalidHttpPipeNamespaceException($e->getMessage());
        }
    }

    public static function assemblePipesFromAnnotations(array $ofClass, array $ofMethods)
    {
        $name = $ofClass['NAME'] ?? null;
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
