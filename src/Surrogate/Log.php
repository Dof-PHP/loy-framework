<?php

declare(strict_types=1);

namespace DOF\Surrogate;

use DOF\DOF;
use DOF\ENV;
use DOF\Convention;
use DOF\Util\FS;
use DOF\Util\Surrogate;
use DOF\Logging\LoggerAware as Instance;
use DOF\Logging\Logger\File;

final class Log extends Surrogate
{
    public static function namespace() : string
    {
        return Instance::class;
    }

    public static function singleton() : bool
    {
        // here we can singleton file logger coz it's stateless
        return true;
    }

    public static function new()
    {
        $proxy = parent::new();
        $logger = new File;

        if ($root = DOF::root()) {
            $logger->setDirectory(FS::path($root, Convention::DIR_RUNTIME));
            $logger->setSingle(ENV::systemGet('FILE_LOGGING_SINGLE', true));
        }

        $proxy->setLogger($logger);

        return $proxy;
    }
}
