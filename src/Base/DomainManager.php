<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Exception;

final class DomainManager
{
    const DOMAIN_DIR  = '__domain__';
    const DOMAIN_FILE = 'domain.php';

    private static $root = '';
    private static $dirs = [];
    private static $namespaces = [];

    public static function compile(string $domainRoot)
    {
        if (! is_dir($domainRoot)) {
            throw new Exception('INVALID_DOMAIN_ROOT');
        }
        self::$root = $domainRoot;
        self::$dirs = [];
        self::$namespaces = [];

        self::findDomains(self::$root);
    }

    private static function findDomains(string $dir, string $lastDomain = null)
    {
        list_dir($dir, function (array $list, string $dir) use ($lastDomain) {
            if (in_array(self::DOMAIN_DIR, $list)) {
                $domain  = join(DIRECTORY_SEPARATOR, [$dir, self::DOMAIN_DIR]);
                $_domain = join(DIRECTORY_SEPARATOR, [$domain, self::DOMAIN_FILE]);
                if (is_dir($domain) && is_file($_domain)) {
                    $lastDomain = $domain;
                    self::$dirs[] = $dir;
                }
            }

            foreach ($list as $pathname) {
                $path = join(DIRECTORY_SEPARATOR, [$dir, $pathname]);
                if (in_array($pathname, ['.', '..', self::DOMAIN_DIR])) {
                    continue;
                }
                if (is_dir($path)) {
                    self::findDomains($path, $lastDomain);
                    continue;
                }

                $ns = get_namespace_of_file($path, true);
                if ($ns) {
                    self::$namespaces[$ns] = $lastDomain;
                }
            }
        });
    }

    public static function getRoot() : string
    {
        return self::$root;
    }

    public static function getDomainByNamespace(string $ns = null) : ?string
    {
        return self::$namespaces[$ns] ?? null;
    }

    public static function getNamespaces() : array
    {
        return self::$namespaces ?? [];
    }

    public static function getDirs() : array
    {
        return self::$dirs ?? [];
    }
}
