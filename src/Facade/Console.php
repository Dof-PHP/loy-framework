<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Cli\Console as Instance;
use Loy\Framework\Cli\Kernel;

class Console extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;

    public static function exception(string $message, array $context = [])
    {
        $context['__meta'] = Kernel::getContext(false);
        Log::log('exception', $message, $context);
        unset($context['__meta']);

        self::getInstance()->exception($message, $context);
    }
}
