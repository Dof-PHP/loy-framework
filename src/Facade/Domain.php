<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Domain as Instance;

class Domain extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
