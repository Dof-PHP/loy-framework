<?php

declare(strict_types=1);

namespace Dof\Framework;

class Tester
{
    /**
     * Check if two array have the same key/value pairs in the same order and of the same types.
     *
     * @return bool
     */
    public function assertArrayEqualWithOrder($compare, $to) : bool
    {
        if (is_array($compare) && is_array($to)) {
            return $compare === $to;
        }

        return false;
    }

    /**
     * Check if two array have the same key/value pairs.
     *
     * @return bool
     */
    public function assertArrayEqual($compare, $to) : bool
    {
        if (is_array($compare) && is_array($to)) {
            return $compare == $to;
        }

        return false;
    }
}
