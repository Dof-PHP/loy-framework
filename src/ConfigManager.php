<?php

declare(strict_types=1);

namespace Loy\Framework;

final class ConfigManager
{
    const DEFAULT_DIR = ['config'];
    const FILE_REGEX  = '#^([a-z]+)\.(php|json|ini|yml|yaml|xml)$#';
    const SUPPORTS    = ['php', 'json', 'xml'];

    private static $default = [];
    private static $domains = [];

    /**
     * Init default/basic configs for domain and framework
     *
     * @param string $root: Absolute path of config dir
     */
    public static function init(string $root)
    {
        if (! is_dir($root)) {
            exception('InvalidProjectRoot', compact('root'));
        }

        self::$default = self::loadDir(ospath($root, self::DEFAULT_DIR));
    }

    /**
     * Load configs from domains
     *
     * @param array $dirs: Domain directories
     */
    public static function load(array $dirs)
    {
        foreach ($dirs as $meta => $domain) {
            self::$domains[$domain] = self::loadDir($meta);
        }
    }

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
