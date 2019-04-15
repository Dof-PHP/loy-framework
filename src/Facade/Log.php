<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\Facade;
use Dof\Framework\Log\LoggerAware as Instance;

class Log extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;

    public static function interpolate(string $message, array $context = []) : string
    {
        // build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            // check that the value can be casted to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}
