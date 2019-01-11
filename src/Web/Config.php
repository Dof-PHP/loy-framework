<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

final class Config
{
    private $data = [];
    
    public static function __callStatic(string $method, array $argvs)
    {
        if (0 === mb_strpos($method, 'get')) {
            $key = mb_strcut($method, 3);

            return self::$data[$key] ?? null;
        }

        $default = $argvs[0] ?? null;

        return $default;
    }
}
