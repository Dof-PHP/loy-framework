<?php

declare(strict_types=1);

namespace Loy\Framework\Core;

class ApplicationService
{
    public static function init($data = null)
    {
        return new static($data);
    }

    public static function __callStatic(string $method, array $argvs = [])
    {
        $service = new static;

        return call_user_func_array([$service, $method], $argvs);
    }
}
