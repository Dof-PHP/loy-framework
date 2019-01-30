<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Exception;
use Loy\Framework\Base\ConfigManager;
use Loy\Framework\Facade\Domain;

final class DomainManager
{
    const DOMAIN_DIR  = 'domain';
    const DOMAIN_FLAG = '__domain__';
    const DOMAIN_FILE = 'domain.php';

    private static $root  = '';
    private static $files = [];
    private static $pool  = [];
    private static $chain = [
        'root' => [],    // domain ancestor (outside domain directory)
        'up'   => [],    // child  => parent
        'down' => [],    // parent => child
    ];
    private static $dirs  = [
        'D' => [],    // domain root only
        'M' => [],    // domain meta dir only
        'M2D' => [],    // domain meta dir => domain root
        'D2M' => [],    // domain root => domain meta dir
    ];
    private static $namespaces = [];

    public static function initFromNamespace(string $namespace)
    {
        $domainMeta = self::$namespaces[$namespace] ?? null;

        return self::initFromDomainMeta($domainMeta);
    }

    public static function initFromDomainMeta(string $domainMeta = null)
    {
        if (! $domainMeta) {
            return zombie_object();
        }

        $domainRoot = self::$dirs['M2D'][$domainMeta] ?? null;

        return self::initFromDomainRoot($domainRoot);
    }

    public static function initFromDomainRoot(string $domainRoot = null)
    {
        if (! $domainRoot) {
            return zombie_object();
        }
        if ($object = (self::$pool[$domainRoot] ?? false)) {
            return $object;
        }

        $domainMeta = self::$dirs['D2M'][$domainRoot] ?? false;
        if (! $domainMeta) {
            return zombie_object();
        }

        return self::$pool[$domainRoot] = Domain::new($domainMeta, $domainRoot, self::$chain);
    }

    public static function initFromFilepath(string $path)
    {
        $domainMeta = self::$files[$path] ?? null;

        return self::initFromDomainMeta($domainMeta);
    }

    public static function compile(string $root)
    {
        $domainRoot = ospath($root, self::DOMAIN_DIR);
        if (! is_dir($domainRoot)) {
            throw new Exception('INVALID_DOMAIN_ROOT');
        }
        self::$root = $domainRoot;
        self::$dirs = [];
        self::$namespaces = [];
        self::$chain['root'] = ConfigManager::getDefaultPath();

        self::find(self::$root, self::$chain['root']);
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
                    if ($last && ($last !== (self::$chain['root'] ?? false))) {
                        self::$chain['up'][$domain] = $last;
                        self::$chain['down'][$last][$domain] = $dir;
                    }
                    $last = $domain;
                    self::$files[$_domain] = $domain;
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

                if ('php' !== pathinfo($path, PATHINFO_EXTENSION)) {
                    continue;
                }

                self::$files[$path] = $last;
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

    public static function getDomainRootByFilePath(string $path = null) : ?string
    {
        $meta = self::getDomainMetaByFilepath($path);

        return $meta ? (self::$dirs['M2D'][$meta] ?? null) : null;
    }

    public static function getDomainMetaByRoot(string $root = null) : ?string
    {
        return self::$dirs['D2M'][$root] ?? null;
    }

    public static function getDomainMetaByFilePath(string $path = null) : ?string
    {
        return self::$files[$path] ?? null;
    }

    public static function getDomainRootByNamespace(string $ns = null) : ?string
    {
        $meta = self::$namespaces[$ns] ?? null;

        return $meta ? (self::$dirs['M2D'][$meta] ?? null) : null;
    }

    public static function getDomainMetaByNamespace(string $ns = null) : ?string
    {
        return self::$namespaces[$ns] ?? null;
    }

    public static function getDomainParentByRoot(string $root = null) : ?string
    {
        $meta = self::getDomainMetaByRoot($root);

        return $meta ? (self::$chain['up'][$meta] ?? null) : null;
    }

    public static function getDomainParentByNamespace(string $ns = null) : ?string
    {
        $meta = self::getDomainMetaByNamespace($ns);

        return $meta ? (self::$chain['up'][$meta] ?? null) : null;
    }

    public static function getDomainParentByFilePath(string $path = null) : ?string
    {
        $meta = self::getDomainMetaByFilePath($path);

        return $meta ? (self::$chain['up'][$meta] ?? null) : null;
    }
 
    public static function getChain() : array
    {
        return self::$chain ?? [];
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
