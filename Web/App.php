<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

class App
{
    public static function run(string $rootPath)
    {
        echo 'current project root absolute path is: ', $rootPath;
    }
}
