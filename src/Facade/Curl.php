<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Curl as Instance;

class Curl extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
