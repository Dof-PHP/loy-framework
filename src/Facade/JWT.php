<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\Facade;
use Dof\Framework\OFB\Auth\JWT as Instance;

class JWT extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
