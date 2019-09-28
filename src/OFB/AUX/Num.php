<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\AUX;

class Num
{
    public static function between($num, $start, $end) : bool
    {
        return ($start <= $num) && ($num <= $end);
    }
}
