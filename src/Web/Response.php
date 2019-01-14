<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Web\Http\Response as Instance;
use Loy\Framework\Core\Facade;

class Response extends Facade
{
    public static $singleton = true;
    public static $namespace = Instance::class;

    private static $wappers = [];

    public static function addWrapper(string $key, array $wrapper)
    {
        self::$wrappers[$key] = $wrapper;
    }
}
