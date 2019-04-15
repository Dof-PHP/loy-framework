<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\Facade;
use Dof\Framework\Validator as Instance;
use Dof\Framework\TypeHint;

class Validator extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
