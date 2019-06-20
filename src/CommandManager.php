<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Facade\Annotation;
use Dof\Framework\Cli\Command\Command;

final class CommandManager
{
    const COMMAND_DIR = 'Command';

    private static $dirs = [];
    private static $commands = [];
    private static $domain = [];
    private static $default = [];

    public static function load(array $dirs)
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$commands, self::$domain, self::$default) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_COMMAND_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([self::$dirs, self::$commands, self::$domain, self::$default], $cache);
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (is_file($cache)) {
            unlink($cache);
        }
    }

    public static function compile(array $dirs, bool $cache = false)
    {
        // Reset
        self::$dirs = [];
        self::$commands = [];
        self::$domain = [];
        self::$default = [];

        self::loadDirs([dirname(get_file_of_namespace(Command::class))], 'default');

        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = ospath($item, self::COMMAND_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        self::loadDirs(self::$dirs, 'domain');

        if ($cache) {
            array2code([self::$dirs, self::$commands, self::$domain, self::$default], Kernel::formatCompileFile(__CLASS__));
        }
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
        $namespace    = $ofClass['namespace']     ?? null;
        $cmdPrefix    = $ofClass['doc']['CMD']    ?? null;
        $commentGroup = $ofClass['doc']['DESC']   ?? null;
        $optionGroup  = $ofClass['doc']['OPTION'] ?? [];

        foreach (($ofMethods ?? []) as $method => $data) {
            $docMethod = $data['doc'] ?? [];
            $cmd = $docMethod['CMD'] ?? null;
            if ((! $docMethod) || (! $cmd)) {
                continue;
            }
            $command = $cmdPrefix ? join('.', [$cmdPrefix, $cmd]) : $cmd;
            $comment = $docMethod['DESC'] ?? null;
            $comment = $commentGroup ? join(': ', [$commentGroup, $comment]) : $comment;
            $options = $docMethod['OPTION'] ?? [];
            $options = array_merge($optionGroup, $options);
            $argvs = $docMethod['ARGV'] ?? [];

            $_cmd = [
                'class'   => $namespace,
                'method'  => $method,
                'comment' => $comment,
                'options' => $options,
                'argvs'   => $argvs,
            ];

            $command = strtolower($command);

            if ($exists = (self::$commands[$command] ?? false)) {
                exception('CommandExistsAlready', [
                    'exists' => $exists,
                    'conflict' => $_cmd,
                ]);
            }

            self::$commands[$command] = $_cmd;

            $idx = count(self::$commands ?? []) - 1;
            if (ci_equal($type, 'domain')) {
                $domain = DomainManager::getKeyByNamespace($namespace);
                self::$domain[$domain][$command] = $idx;
            } else {
                self::$default[$command] = $idx;
            }
        }
    }

    public static function __annotationFilterOption(string $option, array $ext, string $namespace) : ?array
    {
        $ext = array_change_key_case($ext, CASE_UPPER);

        $notes = $ext['NOTES'] ?? '';
        $default = $ext['DEFAULT'] ?? null;

        if ((count($ext) === 1) && empty(array_filter(array_values($ext)))) {
            $notes = array_keys($ext)[0] ?? '';
        }

        return [trim(strtolower($option)) => [
            'NOTES' => $notes,
            'DEFAULT' => $default,
        ]];
    }

    public static function __annotationMultipleMergeOption()
    {
        return true;
    }

    public static function __annotationMultipleOption() : bool
    {
        return true;
    }

    public static function __annotationFilterArgv(string $order, array $ext, string $namespace) : ?array
    {
        $ext = array_change_key_case($ext, CASE_UPPER);

        $notes = $ext['NOTES'] ?? '';
        if ((count($ext) === 1) && empty(array_filter(array_values($ext)))) {
            $notes = array_keys($ext)[0] ?? '';
        }
 
        return [trim($order) => $notes];
    }

    public static function __annotationMultipleMergeArgv()
    {
        return 'index';
    }

    public static function __annotationMultipleArgv() : bool
    {
        return true;
    }

    public static function get(string $name) : ?array
    {
        return self::$commands[$name] ?? null;
    }

    public static function getDomain()
    {
        return self::$domain;
    }

    public static function getDefault()
    {
        return self::$default;
    }

    public static function getCommands()
    {
        return self::$commands;
    }
}
