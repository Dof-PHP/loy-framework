<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

final class Singleton
{
    private static $pool = [];

    public static function get(string $namespace, array $params = [])
    {
        if (! class_exists($namespace)) {
            return null;
        }

        if ($instance = (self::$pool[$namespace] ?? false)) {
            return $instance;
        }

        return self::$pool[$namespace] = new $namespace($params);
    }
}
