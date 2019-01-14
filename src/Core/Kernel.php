<?php

declare(strict_types=1);

namespace Loy\Framework\Core;

use Loy\Framework\Core\DomainManager;
use Loy\Framework\Core\Exception\InvalidProjectRootException;

class Kernel
{
    const DOMAIN_DIR = 'domain';

    protected static $projectRoot = null;

    public static function handle(string $projectRoot)
    {
        throw new InvalidProjectRootException($projectRoot);
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
