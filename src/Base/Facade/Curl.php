<?php

declare(strict_types=1);

namespace Loy\Framework\Base\Facade;

use Loy\Framework\Base\Facade;
use Loy\Framework\Base\Curl as Instance;

class Curl extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
