<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\Facade;
use Dof\Framework\CURL as Instance;

class CURL extends Facade
{
    public static $singleton = false;
    protected static $namespace = Instance::class;
}
