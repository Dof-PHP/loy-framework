<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Annotation as Instance;

class Annotation extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
