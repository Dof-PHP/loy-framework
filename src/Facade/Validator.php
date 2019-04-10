<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Validator as Instance;
use Loy\Framework\TypeHint;

class Validator extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
