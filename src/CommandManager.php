<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;
use Loy\Framework\Cli\Command\Command;

final class CommandManager
{
    const COMMAND_DIR = 'Command';

    private static $dirs = [];
    private static $commands = [
        'default' => [],
        'domain'  => [],
    ];

    public static function compile(array $dirs)
    {
        self::$commands['default'] = [];
        self::loadDirs([dirname(get_file_of_namespace(Command::class))], 'default');

        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs = [];
        self::$commands['domain'] = [];

        array_map(function ($item) {
            $dir = ospath($item, self::COMMAND_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        self::loadDirs(self::$dirs, 'domain');
    }

    public static function loadDirs(array $dirs, string $type)
    {
        // Exceptions may thrown but let invoker to catch for different scenarios
        Annotation::parseClassDirs($dirs, function ($annotations) use ($type) {
            if ($annotations) {
                list($ofClass, , $ofMethods) = $annotations;
                self::assemble($ofClass, $ofMethods, $type);
            }
        }, __CLASS__);
    }

    /**
     * Assemble Repository From Annotations
     */
    public static function assemble(array $ofClass, array $ofMethods, string $type)
    {
        $namespace    = $ofClass['namespace']      ?? null;
        $cmdPrefix    = $ofClass['doc']['CMD']     ?? null;
        $commentGroup = $ofClass['doc']['COMMENT'] ?? null;
        $optionGroup  = $ofClass['doc']['OPTION']  ?? [];

        foreach (($ofMethods ?? []) as $method => $data) {
            $docMethod = $data['doc'] ?? [];
            $cmd = $docMethod['CMD'] ?? null;
            if ((! $docMethod) || (! $cmd)) {
                continue;
            }
            $command = $cmdPrefix ? join('.', [$cmdPrefix, $cmd]) : $cmd;
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

            self::$commands[$type][$command] = [
                'class'   => $namespace,
                'method'  => $method,
                'comment' => $comment,
                'options' => $_options,
            ];
        }
    }

    public static function __annotationParameterFilterOption(array $params = []) : ?array
    {
        $params = array_change_key_case($params, CASE_UPPER);

        if (strtolower($params['DEFAULT'] ?? '') === '__null__') {
            $params['DEFAULT'] = null;
        }

        return $params;
    }

    public static function __annotationMultipleOption() : bool
    {
        return true;
    }

    public static function get(string $name, bool $isDomain = false) : ?array
    {
        return $isDomain
            ? self::$commands['domain'][$name] ?? null
            : self::$commands['default'][$name] ?? null;
    }

    public static function getCommands()
    {
        return self::$commands;
    }
}
