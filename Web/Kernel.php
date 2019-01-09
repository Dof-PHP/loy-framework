<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Exception;

use Loy\Framework\Web\Domain;

class Kernel
{
    public static function handle(string $projectRootPath)
    {
        self::initErrorReporting();

        if (! is_dir($projectRootPath)) {
            throw new Exception('Invalid Project Root!');
        }

        $_SERVER['__LOY_FRAMEWORK_WEB'] = [
            'project_root' => $projectRootPath,
        ];

        Domain::compile($projectRootPath);
    }

    /**
     * @TODO: override from config
     */
    public static function initErrorReporting()
    {
        error_reporting(E_ALL);

        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
    }
}
