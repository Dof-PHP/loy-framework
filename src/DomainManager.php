<?php

declare(strict_types=1);

namespace Loy\Framework;

/**
 * Rules about domain:
 * - One domain CAN NOT contains others, domains are peers
 * - Every domains share a root domain named `Root`
 */
final class DomainManager
{
    const DOMAIN_DIR  = 'domain';
    const DOMAIN_FLAG = '__domain__';
    const DOMAIN_FILE = 'domain.php';

    /** @var string: Domain root */
    private static $root;

    /** @var array: Domain dirs */
    private static $dirs = [];

    /** @var array: Domain meta dirs */
    private static $metas = [];

    /** @var array: Domain keys */
    private static $keys = [];

    /** @var array: PHP files list in domains */
    private static $files = [];

    /** @var array: Namespaces list in domains */
    private static $namespaces = [];

    /** @var array: Domain collection object pool */
    private static $pool = [];

    public static function compile(string $root)
    {
        $domainRoot = ospath($root, self::DOMAIN_DIR);
        if (! is_dir($domainRoot)) {
            exception('InvalidDomainRoot', compact(['root', 'domainRoot']));
        }
        self::$root = $domainRoot;
        self::$dirs = [];
        self::$keys = [];
        self::$metas = [];
        self::$files = [];
        self::$namespaces = [];

        self::find(self::$root);
    }
    
    /**
     * Assemble domain files and namespaces for given domain target
     *
     * @param string $target: target domain absolute path
     * @param string $domain: domain absolute path targets belongs to
     */
    private static function assemble(string $target, string $key)
    {
        list_dir($target, function (array $list, string $dir) use ($key) {
            foreach ($list as $pathname) {
                if (in_array($pathname, ['.', '..', self::DOMAIN_FLAG])) {
                    continue;
                }
                $path = ospath($dir, $pathname);
                if (is_dir($path)) {
                    self::assemble($path, $key);
                    continue;
                }

                if ('php' !== pathinfo($path, PATHINFO_EXTENSION)) {
                    continue;
                }

                $ns = get_namespace_of_file($path, true);
                if ($ns) {
                    // Only save php file with full namespace (classes or interfaces)
                    self::$files[$path]    = $key;
                    self::$namespaces[$ns] = $key;
                }
            }
        });
    }

    /**
     * Find domains in given directory
     *
     * @param string $dir: Directory absolute path
     */
    private static function find(string $target)
    {
        list_dir($target, function (array $list, string $dir) {
            if (in_array(self::DOMAIN_FLAG, $list)) {
                $domain  = ospath($dir, self::DOMAIN_FLAG);
                $_domain = ospath($domain, self::DOMAIN_FILE);
                if ((! is_dir($domain)) || (! is_file($_domain))) {
                    // Ignore invalid domain directory (no domain file)
                    return;
                }

                self::$dirs[] = $dir;
                $key = self::metaToKey($domain);
                self::$metas[$domain] = $key;
                self::$keys[$key] = $dir;
                self::assemble($dir, $key);
            }

            foreach ($list as $pathname) {
                if (in_array($pathname, ['.', '..', self::DOMAIN_FLAG])) {
                    continue;
                }
                $path = ospath($dir, $pathname);
                if (is_dir($path)) {
                    self::find($path);
                }

                // Ignore non-domain files
            }
        });
    }

    public static function metaToKey(string $meta = null) : ?string
    {
        $key = str_replace(self::DOMAIN_FLAG, '', str_replace(self::$root, '', $meta));
        $key = array_trim_from_string($key, DIRECTORY_SEPARATOR);

        return strtolower(join('-', $key));
    }

    public static function hasFile(string $path = null) : bool
    {
        return isset(self::$files[$path]);
    }

    public static function hasNamesapce(string $ns = null) : bool
    {
        return isset(self::$namespaces[$ns]);
    }

    public static function hasKey(string $key = null) : bool
    {
        return isset(self::$keys[$key]);
    }

    public static function collectByKey(string $key = null)
    {
        if (! $key) {
            return null;
        }
        if ($domain = (self::$pool[$key] ?? false)) {
            return $domain;
        }

        return self::$pool[$key] = new class($key) {
            private $key;
            public function __construct(string $key)
            {
                $this->key = $key;
            }
            public function config(string $type = null)
            {
                return $type ? $this->__config()->get($type) : $this->__config();
            }
            private function __config()
            {
                return new class($this->key) {
                    private $domain;
                    public function __construct(string $domain)
                    {
                        $this->domain = $domain;
                    }
                    public function get(string $key)
                    {
                        $config = ConfigManager::getDomainFinalByKey($this->domain, $key);

                        return is_array($config) ? collect($config, $this) : $config;
                    }
                    public function __get(string $attr)
                    {
                        return $this->get($attr);
                    }
                    public function __collectionGet(string $key, Collection $collector)
                    {
                        return $this->get($key);
                    }
                };
            }

            public function __get(string $attr)
            {
                if ('config' === $attr) {
                    return $this->__config();
                }

                return $this->{$attr} ?? null;
            }
        };
    }

    public static function collectByNamespace(string $ns = null)
    {
        return self::collectByKey(self::$namespaces[$ns] ?? null);
    }

    public static function collectByFile(string $path = null)
    {
        return self::collectByKey(self::$files[$path] ?? null);
    }

    public static function getByKey(string $key = null) : ?string
    {
        return self::$keys[$key] ?? null;
    }

    public static function getByFile(string $path = null) : ?string
    {
        return self::$keys[self::$files[$path] ?? null] ?? null;
    }

    public static function getByNamespace(string $ns = null) : ?string
    {
        return self::$keys[self::$namespaces[$ns] ?? null] ?? null;
    }

    public static function getKeyByMeta(string $meta = null) : ?string
    {
        return self::$metas[$meta] ?? null;
    }

    public static function getKeyByFile(string $path = null) : ?string
    {
        return self::$files[$path] ?? null;
    }

    public static function getKeyByNamespace(string $ns = null) : ?string
    {
        return self::$namespaces[$ns] ?? null;
    }

    public static function getNamespaces() : array
    {
        return self::$namespaces;
    }

    public static function getFiles() : array
    {
        return self::$files;
    }

    public static function getKeys() : array
    {
        return self::$keys;
    }

    public static function getMetas() : array
    {
        return self::$metas;
    }

    public static function getDirs() : array
    {
        return self::$dirs;
    }

    public static function getRoot() : ?string
    {
        return self::$root;
    }
}
