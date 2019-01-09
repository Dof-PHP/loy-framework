<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

class App
{
    public function errorReporting()
    {
        error_reporting(E_ALL);

        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
    }
}
