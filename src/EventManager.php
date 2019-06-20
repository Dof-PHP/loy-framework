<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Facade\Annotation;

final class EventManager
{
    const EVENT_DIR = 'Event';

    private static $dirs = [];
    private static $events = [];

    public static function load(array $dirs)
    {
        $cache = Kernel::formatCacheFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$events) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_EVENT_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([self::$dirs, self::$events], $cache);
        }
    }

    public static function getDirs()
    {
        return self::$dirs;
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
        // Reset
        self::$dirs = [];
        self::$events = [];

        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = ospath($item, self::EVENT_DIR);
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
            array2code([self::$dirs, self::$events], Kernel::formatCacheFile(__CLASS__));
        }
    }

    public static function assemble(array $ofClass, array $ofProperties)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if ($exists = (self::$events[$namespace] ?? false)) {
            exception('DuplicateEventNamespace', ['namespace' => $namespace]);
        }
        if (! ($ofClass['doc']['TITLE'] ?? false)) {
            exception('MissingEventTitle', ['event' => $namespace]);
        }

        self::$events[$namespace]['meta'] = $ofClass['doc'] ?? [];

        foreach ($ofProperties as $name => $attrs) {
            self::$events[$namespace]['properties'][$name] = $attrs['doc'] ?? [];
        }
    }

    public static function __annotationMultipleListener() : bool
    {
        return true;
    }

    public static function __annotationFilterListener(string $listener, array $argvs, string $namespace)
    {
        $_listener = get_annotation_ns($listener, $namespace);
        if (! $_listener) {
            exception('EventListenerNotExists', compact('listener'));
        }
        if (! is_subclass_of($_listener, Listener::class)) {
            exception('InvalidEventListenerNotExists', compact('listener', '_listener'));
        }

        return $_listener;
    }

    public static function getEvents()
    {
        return self::$events;
    }

    public static function get(string $namespace)
    {
        return self::$events[$namespace] ?? null;
    }
}
