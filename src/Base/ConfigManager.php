<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\Exception\InvalidProjectRootException;

final class ConfigManager
{
    const DEFAULT_DIR    = ['config', 'domain'];
    const FRAMEWORK_DIR  = ['config', 'framework'];
    const FILENAME_REGEX = '#^([a-z]+)\.php$#';

    private static $default   = [];
    private static $framework = [];
    private static $domains   = [];
    private static $path = [
        'default'   => null,
        'framework' => null,
    ];

    /**
     * Load configs from domains
     *
     * @param $dirs Array (Domain Root => Domain Meta Path)
     */
    public static function load(array $dirs)
    {
        foreach ($dirs as $domain => $dir) {
            self::$domains[$domain] = self::loadDir($dir);
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
            throw new InvalidProjectRootException($root);
        }

        self::$default = collect(self::loadDir(
            self::$path['default'] = ospath($root, self::DEFAULT_DIR)
        ));
        self::$framework = collect(self::loadDir(
            self::$path['framework'] = ospath($root, self::FRAMEWORK_DIR)
        ));
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

    public static function getLatestByDomainRoot(string $root, string $key, $default = null)
    {
        // TODO
    }

    public static function getLatestByFilePath(string $path, string $key, $default = null)
    {
        // TODO
    }

    public static function getLatestByNamespace(string $namespace, string $key, $default = null)
    {
    }

    public static function get(string $key = 'domain')
    {
        if ($key === 'domain') {
            return self::getDefault();
        }
        if ($key === 'framework') {
            return self::getFramework();
        }

        return self::getDomain($key);
    }

    public static function getDomain(string $domain)
    {
        return self::$domains[$domain] ?? [];
    }

    public static function getDomains()
    {
        return self::$domains;
    }

    public static function getFramework()
    {
        return self::$framework;
    }

    public static function getFrameworkPath()
    {
        return self::$path['framework'];
    }

    public static function getDefault()
    {
        return self::$default;
    }

    public static function getDefaultPath()
    {
        return self::$path['default'];
    }
}
