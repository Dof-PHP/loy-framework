<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

abstract class Facade
{
    private static $pool = [];

    public static function __callStatic(string $method, array $argvs = [])
    {
        return call_user_func_array([self::getInstance(), $method], $argvs);
    }

    public static function getInstance()
    {
        $ns = self::proxy('namespace');
        $singleton = self::proxy('singleton');
        if (! $singleton) {
            $ns = self::proxy('namespace');
            return new $ns;
        }

        if (! (self::$pool[static::class] ?? false) instanceof $ns) {
            self::$pool[static::class] = new $ns;
        }

        return self::$pool[static::class];
    }

    public static function proxy(string $attr)
    {
        $child = static::class;

        return $child::${$attr};
    }

    public static function new()
    {
        return new static;
    }
}
