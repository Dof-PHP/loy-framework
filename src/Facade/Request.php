<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\Facade;
use Loy\Framework\Web\Request as Instance;

class Request extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
