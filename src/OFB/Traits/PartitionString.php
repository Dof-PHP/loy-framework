<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Traits;

trait PartitionString
{
    protected static $moduloDividendLength = 14;

    public static function select(array &$nodes, string $key) : int
    {
        sort($nodes);

        return (self::hash($key) % count($nodes));
    }

    public static function hash(string $key) : int
    {
        return hexdec(substr(md5($key), 0, self::$moduloDividendLength));
    }
}
