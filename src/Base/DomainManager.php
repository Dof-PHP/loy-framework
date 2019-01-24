<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Exception;
use Loy\Framework\Base\ConfigManager;

final class DomainManager
{
    const DOMAIN_DIR  = 'domain';
    const DOMAIN_FLAG = '__domain__';
    const DOMAIN_FILE = 'domain.php';

    private static $root = '';
    private static $dirs = [
        'D' => [],    // domain root only
        'M' => [],    // meta dir only
        'M2D' => [],    // meta dir    => domain root
        'D2M' => [],    // domain root => meta dir
    ];
    private static $namespaces = [];

    public static function compile(string $root)
    {
        $domainRoot = ospath($root, self::DOMAIN_DIR);
        if (! is_dir($domainRoot)) {
            throw new Exception('INVALID_DOMAIN_ROOT');
        }
        self::$root = $domainRoot;
        self::$dirs = [];
        self::$namespaces = [];

        self::find(self::$root, ConfigManager::getDefaultPath());
    }

    /**
     * Find domains in given directory
     *
     * @param $dir String Derectory absolute path
     * @param $last String Last domain absolute path
     */
    private static function find(string $dir, string $last = null)
    {
        list_dir($dir, function (array $list, string $dir) use ($last) {
            if (in_array(self::DOMAIN_FLAG, $list)) {
                $domain  = ospath($dir, self::DOMAIN_FLAG);
                $_domain = ospath($domain, self::DOMAIN_FILE);
                if (is_dir($domain) && is_file($_domain)) {
                    $last = $domain;
                    self::$dirs['D'][] = $dir;
                    self::$dirs['M'][] = $domain;
                    self::$dirs['D2M'][$dir]    = $domain;
                    self::$dirs['M2D'][$domain] = $dir;
                }
            }

            foreach ($list as $pathname) {
                $path = ospath($dir, $pathname);
                if (in_array($pathname, ['.', '..', self::DOMAIN_FLAG])) {
                    continue;
                }
                if (is_dir($path)) {
                    self::find($path, $last);
                    continue;
                }

                $ns = get_namespace_of_file($path, true);
                if ($ns) {
                    self::$namespaces[$ns] = $last;
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

    public static function getDirsD2M() : array
    {
        return self::$dirs['D2M'] ?? [];
    }

    public static function getDirsM2D() : array
    {
        return self::$dirs['M2D'] ?? [];
    }

    public static function getMetaDirs() : array
    {
        return self::$dirs['M'] ?? [];
    }

    public static function getDirs() : array
    {
        return self::$dirs['D'] ?? [];
    }
}
