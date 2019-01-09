<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

class Kernel
{
    public static function handle(string $projectRootPath)
    {
        $projectRootPath;
    }

    public static function getCacheDirectory() : string
    {
        return 'var/cache/web';
    }

    public static function getLogDirectory() : string
    {
        return 'storage/log';
    }

    public static function getDomainsDirectory() : string
    {
        return 'domains';
    }
}
