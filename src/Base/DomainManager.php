<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Exception;

final class DomainManager
{
    const DOMAIN_DIR  = '__domain__';
    const DOMAIN_FILE = 'domain.php';

    private static $domainRoot = '';
    private static $domains    = [];

    public static function compile(string $domainRoot)
    {
        if (! is_dir($domainRoot)) {
            throw new Exception('INVALID_DOMAIN_ROOT');
        }
        self::$domainRoot = $domainRoot;

        self::findDomains(self::$domainRoot);
    }

    private static function findDomains(string $dir)
    {
        list_dir($dir, function (array $list, string $dir) {
            if (in_array(self::DOMAIN_DIR, $list)) {
                $domain = join(DIRECTORY_SEPARATOR, [$dir, self::DOMAIN_DIR, self::DOMAIN_FILE]);
                if (is_file($domain)) {
                    self::$domains[] = $dir;
                }
            }

            foreach ($list as $pathname) {
                $path = join(DIRECTORY_SEPARATOR, [$dir, $pathname]);
                if (in_array($pathname, ['.', '..'])) {
                    continue;
                }
                if (is_dir($path)) {
                    self::findDomains($path);
                }
            }
        });
    }

    public static function getDomainRoot() : string
    {
        return self::$domainRoot;
    }

    public static function getDomains() : array
    {
        return self::$domains;
    }
}
