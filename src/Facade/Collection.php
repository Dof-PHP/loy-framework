<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Base\Facade;
use Loy\Framework\Base\Collection as Instance;

class Collection extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
