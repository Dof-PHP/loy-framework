<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\DomainManager;
use Loy\Framework\Base\Exception\InvalidProjectRootException;

class Kernel
{
    const DOMAIN_DIR = 'domain';

    protected static $projectRoot = null;

    public static function handle(string $projectRoot)
    {
        if (! is_dir($projectRoot)) {
            throw new InvalidProjectRootException($projectRoot);
        }
        self::$projectRoot = $projectRoot;

        self::compileDomains();
    }

    public static function compileDomains()
    {
        $domainRoot = join(DIRECTORY_SEPARATOR, [self::$projectRoot, self::DOMAIN_DIR]);
        DomainManager::compile($domainRoot);
    }

    public static function getProjectRoot()
    {
        return self::$projectRoot;
    }
}
