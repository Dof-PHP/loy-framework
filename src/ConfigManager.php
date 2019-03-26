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
        return array_get_by_chain_key(self::$data, $key, '.');
    }

    public static function getDomainByKey(string $domain = null, string $key = null)
    {
        $key = is_null($key) ? $domain : "{$domain}.{$key}";

        return array_get_by_chain_key(self::$domains, $key, '.');
    }

    public static function getDomainByFile(string $file, string $key = null)
    {
        $domain = DomainManager::getKeyByFile($file);

        return self::getDomainByKey($domain, $key);
    }

    public static function getDomainByNamespace(string $ns, string $key = null)
    {
        $domain = DomainManager::getKeyByNamesapce($ns);

        return self::getDomainByKey($domain, $key);
    }

    public static function getDomainFinalByNamespace(string $ns, string $key = null)
    {
        return self::getDomainByNamesapce($ns) ?: self::getDefault($key);
    }

    public static function getDomainFinalByFile(string $file, string $key = null)
    {
        return self::getDomainByFile($file, $key) ?: self::getDefault($key);
    }

    public static function getDomainFinalByKey(string $domain, string $key = null)
    {
        return self::getDomainByKey($domain, $key) ?: self::getDefault($key);
    }

    public static function getDomains()
    {
        return self::$domains;
    }
}
