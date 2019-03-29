<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;

final class CommandManager
{
    const COMMAND_DIR = 'Command';

    private static $dirs = [];
    private static $commands = [];

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs = [];
        self::$commands = [];

        array_map(function ($item) {
            $dir = ospath($item, self::COMMAND_DIR);
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
     * Assemble Repository From Annotations
     */
    public static function assemble(array $ofClass, array $ofMethods)
    {
        $namespace    = $ofClass['namespace']      ?? null;
        $cmdPrefix    = $ofClass['doc']['CMD']     ?? null;
        $commentGroup = $ofClass['doc']['COMMENT'] ?? null;
        $optionGroup  = $ofClass['doc']['OPTION']  ?? [];

        foreach (($ofMethods['self'] ?? []) as $method => $data) {
            $docMethod = $data['doc'] ?? [];
            $cmd = $docMethod['CMD'] ?? null;
            if ((! $docMethod) || (! $cmd)) {
                continue;
            }
            $command = join('.', [$cmdPrefix, $cmd]);
            $comment = $docMethod['COMMENT'] ?? null;
            $comment = join(': ', [$commentGroup, $comment]);
            $options = $docMethod['OPTION']  ?? [];
            $options = array_merge($optionGroup, $options);

            $_options = [];
            foreach ($options as $option) {
                $name = $option['NAME'] ?? false;
                if (! $name) {
                    continue;
                }
                unset($option['NAME']);

                $_options[$name] = $option;
            }

            self::$commands[$command] = [
                'class'   => $namespace,
                'method'  => $method,
                'comment' => $comment,
                'options' => $_options,
            ];
        }
    }

    public static function __annotationFilterOption(string $option, array $params = []) : ?array
    {
        $params = array_change_key_case($params, CASE_UPPER);

        if (strtolower($params['DEFAULT'] ?? '') === '__null__') {
            $params['DEFAULT'] = null;
        }

        $params['NAME'] = $option;

        return $params;
    }

    public static function __annotationMultipleOption() : bool
    {
        return true;
    }

    public static function get(string $name) : ?array
    {
        return self::$commands[$name] ?? null;
    }

    public static function getCommands()
    {
        return self::$commands;
    }
}
