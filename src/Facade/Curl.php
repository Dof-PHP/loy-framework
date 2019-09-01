<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\Facade;
use Dof\Framework\Curl as Instance;

class Curl extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
