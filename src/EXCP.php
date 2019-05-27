<?php

declare(strict_types=1);

namespace Dof\Framework;

use Throwable;

class EXCP
{
    const VIOLATED_UNIQUE_CONSTRAINT = 'ViolatedUniqueConstraint';

    public static function is(Throwable $e, string $name) : bool
    {
        return is_exception($e, $name);
    }
}
