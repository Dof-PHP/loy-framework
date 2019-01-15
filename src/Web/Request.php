<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Web\Http\Request as Instance;
use Loy\Framework\Base\Facade;

class Request extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;
}
