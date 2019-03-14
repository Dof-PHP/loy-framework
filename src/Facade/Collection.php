<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Collection as Instance;

class Collection extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
