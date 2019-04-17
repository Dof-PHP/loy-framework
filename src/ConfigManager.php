<?php

declare(strict_types=1);

namespace Dof\Framework;

final class ConfigManager
{
    const DEFAULT_DIR = ['config'];
    const FILE_REGEX  = '#^([a-z]+)\.(php|json|ini|yml|yaml|xml)$#';
    const SUPPORTS    = ['php', 'json', 'xml'];

    private static $default = [];
    private static $domains = [];

    /**
     * Init default/basic configs for domain and framework with cache management
     *
     * @param string $root: Absolute path of project root
     */
    public static function init(string $root)
    {
        if (! is_dir($root)) {
            exception('InvalidProjectRoot', compact('root'));
        }
        $cache = Kernel::formatCacheFile(__CLASS__, 'default');
        if (is_file($cache)) {
            self::$default = load_php($cache);
            return;
        }

        self::compileDefault($root);

        if (self::matchEnv(['ENABLE_CONFIG_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code(self::$default, $cache);
        }
    }

    /**
     * Compile default configs for domain and framework
     *
     * @param string $root: Absolute path of project root
     */
    public static function compileDefault(string $root, bool $cache = false)
    {
        self::$default = self::loadDir(ospath($root, self::DEFAULT_DIR));

        if ($cache) {
            array2code(self::$default, Kernel::formatCacheFile(__CLASS__, 'default'));
        }
    }

    /**
     * Load configs from domains with cache management
     *
     * @param array $dirs: Domain directories
     */
    public static function load(array $dirs)
    {
        $cache = Kernel::formatCacheFile(__CLASS__, 'domains');
        if (file_exists($cache)) {
            self::$domains = load_php($cache);
            return;
        }

        self::compileDomains($dirs);

        if (self::matchEnv(['ENABLE_CONFIG_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code(self::$domains, $cache);
        }
    }

    /**
     * Compile configurations of domains
     *
     * @param array $dirs: Domain directories
     */
    public static function compileDomains(array $dirs, bool $cache = false)
    {
        foreach ($dirs as $meta => $domain) {
            self::$domains[$domain] = self::loadDir($meta);
        }

        if ($cache) {
            array2code(self::$domains, Kernel::formatCacheFile(__CLASS__, 'domains'));
        }
    }

    /**
     * Load configuration files from a directory with no cache management
     *
     * @param stirng $path
     */
    public static function loadDir(string $path)
    {
        $result = [];
        if (is_dir($path)) {
            list_dir($path, function ($list, $dir) use (&$result) {
                foreach ($list as $filename) {
                    if (in_array($filename, ['.', '..'])) {
                        continue;
                    }
                    $path = ospath($dir, $filename);
                    $matches = [];
                    if (1 === preg_match(self::FILE_REGEX, $filename, $matches)) {
                        $key  = $matches[1] ?? false;
                        $type = $matches[2] ?? false;
                        if ($key && $type && in_array($type, self::SUPPORTS)) {
                            $result[$key] = self::loadFile($path, $type);
                        }
                        continue;
                    }

                    // ignore sub-dirs
                }
            });
        }

        return $result;
    }

    /**
     * Read raw configs by file path and config type
     *
     * @param string $file: Config file path
     * @param string $type: Config file type
     * @return array: Final configs
     */
    public static function loadFile(string $file, string $type) : array
    {
        switch ($type) {
            case 'php':
                return load_php($file);
            case 'json':
                return dejson($file, true, true);
            case 'xml':
                return dexml($file, false, true);
            default:
                return [];
        }
    }

    public static function flush()
    {
        $default = Kernel::formatCacheFile(__CLASS__, 'default');
        if (is_file($default)) {
            unlink($default);
        }
        $domains = Kernel::formatCacheFile(__CLASS__, 'domains');
        if (is_file($domains)) {
            unlink($domains);
        }
    }

    public static function get(string $key)
    {
        return array_get_by_chain_key(self::$default, $key, '.');
    }

    public static function getDomainByKey(string $domain = null, string $key = null, $default = null)
    {
        if (! $domain) {
            return null;
        }

        $config = self::$domains[$domain] ?? null;

        if (is_null($key)) {
            return $config;
        }

        return array_get_by_chain_key($config, $key, '.') ?: $default;
    }

    public static function getDomainByFile(string $file, string $key = null, $default = null)
    {
        return self::getDomainByKey(DomainManager::getKeyByFile($file), $key, $default);
    }

    public static function getDomainByNamespace(string $ns, string $key = null, $default = null)
    {
        return self::getDomainByKey(DomainManager::getKeyByNamespace($ns), $key, $default);
    }

    public static function getDomainFinalByNamespace(string $ns, string $key = null, $default = null)
    {
        return self::getDomainByNamespace($ns, $key, $default) ?: self::getDefault($key, $default);
    }

    public static function getDomainFinalByFile(string $file, string $key = null, $default = null)
    {
        return self::getDomainByFile($file, $key, $default) ?: self::getDefault($key, $default);
    }

    public static function getDomainFinalByKey(string $domain, string $key = null, $default = null)
    {
        return self::getDomainByKey($domain, $key, $default) ?: self::getDefault($key, $default);
    }

    public static function getDomainFinalEnvByNamespace(string $ns, string $key = null, $default = null)
    {
        $key = 'env.'.$key;

        return self::getDomainFinalByNamespace($ns, $key, $default);
    }

    public static function getDomainFinalEnvByFile(string $file, string $key = null, $default = null)
    {
        $key = 'env.'.$key;

        return self::getDomainFinalByFile($file, $key, $default);
    }

    public static function getDomainFinalEnvByKey(string $domain, string $key = null, $default = null)
    {
        $key = 'env.'.$key;

        return self::getDomainFinalByKey($domain, $key, $default);
    }
    public static function getDomainFinalDomainByNamespace(string $ns, string $key = null, $default = null)
    {
        $key = 'domain.'.$key;

        return self::getDomainFinalByNamespace($ns, $key, $default);
    }

    public static function getDomainFinalDomainByFile(string $file, string $key = null, $default = null)
    {
        $key = 'domain.'.$key;

        return self::getDomainFinalByFile($file, $key, $default);
    }

    public static function getDomainFinalDomainByKey(string $domain, string $key = null, $default = null)
    {
        $key = 'domain.'.$key;

        return self::getDomainFinalByKey($domain, $key, $default);
    }

    public static function getDomainFinalDatabaseByNamesapce(string $ns, string $key = null, $default)
    {
        return self::getDomainFinalByNamespace($ns, "database.{$key}", $default);
    }

    public static function getDomainFinalDatabaseByFile(string $file, string $key = null, $default = null)
    {
        return self::getDomainFinalByFile($file, "database.{$key}", $default);
    }

    public static function getDomainFinalDatabaseByKey(string $domain, string $key = null, $default = null)
    {
        return self::getDomainFinalByKey($domain, "database.{$key}", $default);
    }

    /**
     * Match a env config item by a list of keys with order
     *
     * @param array $keys: The list of keys to match
     * @param mixed $default
     */
    public static function matchEnv(array $keys = [], $default = null)
    {
        foreach ($keys as $key) {
            if (is_string($key)) {
                continue;
            }

            $val = self::getEnv($key);
            if (! is_null($val)) {
                return $val;
            }
        }

        return $default;
    }

    public static function getEnv(string $key = null, $default = null)
    {
        return array_get_by_chain_key(self::$default['env'] ?? [], $key) ?: $default;
    }

    public static function getFramework(string $key = null, $default = null)
    {
        return array_get_by_chain_key(self::$default['framework'] ?? [], $key) ?: $default;
    }

    /**
     * Get gobal domain config
     *
     * @param string $key: Config key
     * @param mixed $default: Default value when config item not found
     */
    public static function getDomain(string $key = null, $default = null)
    {
        return array_get_by_chain_key(self::$default['domain'] ?? [], $key) ?: $default;
    }

    public static function getDomains()
    {
        return self::$domains;
    }

    public static function getDefault(string $key = null, $default = null)
    {
        return $key ? (array_get_by_chain_key(self::$default, $key) ?: $default) : self::$default;
    }
}
