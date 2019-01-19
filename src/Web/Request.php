<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Base\Facade;
use Loy\Framework\Web\Http\Request as Instance;

class Request extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;

    /**
     * Create a new fresh request instance
     */
    public static function new() : Instance
    {
        return new self::$namespace;
    }

    /**
     * Make a request to inner HTTP ports
     *
     */
    public static function make(
        string $type,
        string $uri,
        array $params = [],
        array $headers = []
    ) {
        $type = strtoupper($type);
        if (! in_array($type, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
            return false;
        }

        return [];
    }
}
