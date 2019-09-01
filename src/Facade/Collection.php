<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\Facade;
use Dof\Framework\Collection as Instance;

class Collection extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
