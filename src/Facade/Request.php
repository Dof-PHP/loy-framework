<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Web\Request as Instance;

class Request extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;

    /**
     * Proxy match method of instance for variable reference passing
     */
    public function match(array $keys = [], string &$_key = null)
    {
        return self::getInstance()->match($keys, $_key);
    }
}
