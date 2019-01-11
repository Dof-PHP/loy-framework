<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Web\Http\Request as Instance;
use Loy\Framework\Core\Facade;

class Request extends Facade
{
    public static $singleton = true;
    public static $namespace = Instance::class;
}
