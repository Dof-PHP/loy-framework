<?php

declare(strict_types=1);

namespace DOF;

use DOF\Convention;
use DOF\Traits\Config;

final class INI
{
    use Config;
    
    const ROOT = Convention::DIR_SETTING;
}
