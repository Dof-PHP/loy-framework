<?php

declare(strict_types=1);

namespace DOF\Exceptor;

use DOF\Util\Exceptor;

class DOFInitExceptor extends Exceptor
{
    public $advices = [
        'INVALID_TIMEZONE' => 'See: timezone_identifiers_list()',
    ];
}
