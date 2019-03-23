<?php

declare(strict_types=1);

namespace Loy\Framework;

final class ConfigManager
{
    const DEFAULT_DIR    = ['domain', 'Root', '__domain__'];
    const FRAMEWORK_DIR  = ['framework', 'config'];
    const FILENAME_REGEX = '#^([a-z]+)\.php$#';

    private static $data = [
        'default'   => [],
        'framework' => [],
    ];
    private static $path = [
        'default'   => null,
        'framework' => null,
    ];
    private static $domains = [];

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

    /**
     * Init default/basic configs for domain and framework
     *
     * @param $root String (Absolute dir path)
     */
    public static function init(string $root)
    {
        if (! is_dir($root)) {
            exception('InvalidProjectRoot', compact('root'));
        }

        self::$data['default'] = self::loadDir(
            self::$path['default'] = ospath($root, self::DEFAULT_DIR)
        );
        self::$data['framework'] = self::loadDir(
            self::$path['framework'] = ospath($root, self::FRAMEWORK_DIR)
        );
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
                    if (1 === preg_match(self::FILENAME_REGEX, $filename, $matches)) {
                        $key = $matches[1] ?? false;
                        if ($key) {
                            $result[$key] = load_php($path);
                        }
                        continue;
                    }

                    // ignore sub-dirs
                }
            });
        }

        return $result;
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

    public static function getDefault(string $key = null)
    {
        return is_null($key) ? self::$data['default'] : array_get_by_chain_key(self::$data['default'], $key, '.');
    }

    public static function getFramework(string $key = null)
    {
        return is_null($key) ? self::$data['framework'] : array_get_by_chain_key(self::$data['framework'], $key, '.');
    }

    public static function getDefaultPath()
    {
        return self::$path['default'];
    }

    public static function getFrameworkPath()
    {
        return self::$path['framework'];
    }
}
