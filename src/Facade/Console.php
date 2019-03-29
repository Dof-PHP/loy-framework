<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Cli\Console as Instance;

class Console extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;

    public static function exception(string $message, array $context = [])
    {
        Log::log('exception', $message, $context);

        self::getInstance()->exception($message, $context);
    }
}
