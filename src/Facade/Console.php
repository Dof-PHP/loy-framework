<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\Facade;
use Dof\Framework\Cli\Console as Instance;
use Dof\Framework\Cli\Kernel;

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
