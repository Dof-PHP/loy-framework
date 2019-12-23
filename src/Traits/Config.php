<?php

declare(strict_types=1);

namespace DOF\Traits;

use DOF\DOF;
use DOF\DMN;
use DOF\Convention;
use DOF\Traits\CompileCache;
use DOF\Util\FS;
use DOF\Util\Str;
use DOF\Util\Arr;

trait Config
{
    use CompileCache;
    
    private static $system = [];
    private static $domain = [];
    private static $vendor = [];

    public static function load(bool $_cache = false)
    {
        if (\is_file($cache = self::formatCompileFile())) {
            list(self::$system, self::$domain, self::$vendor) = Arr::load($cache);
            return;
        }

        self::init($_cache);
    }

    public static function init(bool $cache = false)
    {
        // reset
        self::$system = [];
        self::$domain = [];
        self::$vendor = [];

        $path = DOF::path(static::ROOT);

        self::systemLoad($path);
        self::domainLoad(FS::path($path, Convention::SRC_DOMAIN));
        self::vendorLoad(FS::path($path, Convention::SRC_VENDOR));

        if ($cache) {
            Arr::save([self::$system, self::$domain, self::$vendor], self::formatCompileFile());
        }
    }

    // Load global/system/default configs
    public static function systemLoad(string $path)
    {
        if (! \is_dir($path)) {
            return;
        }

        FS::ls(function ($list, $dir) {
            foreach ($list as $name) {
                $path = FS::path($dir, $name);
                if (!\is_file($path)) {
                    continue;
                }
                $matches = [];
                if (1 === \preg_match(Convention::REGEX_CONFIG_FILE, $name, $matches)) {
                    if ($item = \strtolower($matches[1] ?? '')) {
                        $data = Arr::load($path);
                        self::$system[$item] = ($item === 'env') ? \array_change_key_case($data, CASE_UPPER) : $data;
                    }
                }
            }
        }, $path);
    }

    // Load configs of domains
    public static function domainLoad(string $path)
    {
        if ((! \is_dir($path)) || (! ($root = DOF::path(Convention::DIR_DOMAIN))) || (! \is_dir($root))) {
            return;
        }

        foreach (DMN::list() as $domain => $dir) {
            $_path = Str::shift($dir, $root);
            $_path = FS::path($path, Str::arr($_path, FS::DS));
            if (! \is_dir($_path)) {
                continue;
            }
            FS::ls(function ($list, $dir) use ($domain) {
                foreach ($list as $item) {
                    $path = FS::path($dir, $item);
                    if (! \is_file($path)) {
                        continue;
                    }
                    $matches = [];
                    if (1 === \preg_match(Convention::REGEX_CONFIG_FILE, $item, $matches)) {
                        if ($_item = \strtolower($matches[1] ?? '')) {
                            $data = Arr::load($path);
                            self::$domain[$domain][$_item] = ($_item === 'env') ? \array_change_key_case($data, CASE_UPPER) : $data;
                        }
                    }
                }
            }, $_path);
        }
    }

    // Load configs for DOF vendor packages
    public static function vendorLoad(string $path)
    {
        if (! \is_dir($path)) {
            return;
        }

        FS::ls(function ($list, $dir) {
            foreach ($list as $vendor) {
                $path = FS::path($dir, $vendor);
                if (! \is_dir($path)) {
                    continue;
                }
                FS::ls(function ($list, $dir) use ($vendor) {
                    foreach ($list as $package) {
                        $path = FS::path($dir, $package);
                        if (! \is_dir($path)) {
                            continue;
                        }
                        FS::ls(function ($list, $dir) use ($vendor, $package) {
                            foreach ($list as $item) {
                                $path = FS::path($dir, $item);
                                if (!\is_file($path)) {
                                    continue;
                                }
                                $matches = [];
                                if (1 === \preg_match(Convention::REGEX_CONFIG_FILE, $item, $matches)) {
                                    if ($_item = \strtolower($matches[1] ?? '')) {
                                        $data = Arr::load($path);
                                        self::$vendor["{$vendor}/{$package}"][$_item] = ($_item === 'env') ? \array_change_key_case($data, CASE_UPPER) : $data;
                                    }
                                }
                            }
                        }, $path);
                    }
                }, $path);
            }
        }, $path);
    }

    public static function all()
    {
        return [
            Convention::SRC_SYSTEM => self::$system,
            Convention::SRC_DOMAIN => self::$domain,
            Convention::SRC_VENDOR => self::$vendor,
        ];
    }

    public static function systemMatch(string $item, array $keys, $default = null, string &$_key = null)
    {
        $data = self::$system[\strtolower($item)] ?? [];

        return Arr::match(\array_map('strtoupper', $keys), $data, $default, $_key);
    }

    public static function systemGet(string $item = null, string $key = null, $default = null)
    {
        if (\is_null($item)) {
            return self::$system ?? [];
        }
        $data = self::$system[\strtolower($item)] ?? null;
        if (\is_null($key)) {
            return $data;
        }
        if (\is_null($data)) {
            return $default;
        }

        return Arr::get($key, $data, $default);
    }
   
    public static function systemSet(string $item, array $data)
    {
        self::$system[\strtolower($item)] = $data;
    }

    public static function domainMatch(string $domain, string $item, array $keys, $default = null, string &$_key = null)
    {
        $name = DMN::name($domain);
        if (\is_null($name)) {
            return $default;
        }

        $data = self::$domain[$name][\strtolower($item)] ?? [];

        return Arr::match(\array_map('strtoupper', $keys), $data, $default, $_key);
    }
    
    public static function finalMatch(string $domain, string $item, array $keys, $default = null, string &$_key = null)
    {
        $value = self::domainMatch($domain, $item, $keys, null, $_key);
        if (\is_null($value)) {
            return self::systemMatch($item, $keys, $default, $_key);
        }

        return $value;
    }

    /**
     * Get domain final business config item: domain -> system
     */
    public static function final(string $domain = null, string $item = null, string $key = null, $default = null)
    {
        $value = self::domainGet($domain, $item, $key);
        if (\is_null($value)) {
            return self::systemGet($item, $key, $default);
        }

        return $value;
    }

    /**
     * Get domain business configs
     */
    public static function domainGet(string $domain = null, string $item = null, string $key = null, $default = null)
    {
        if (\is_null($domain)) {
            return self::$domain ?? [];
        }
        $name = DMN::name($domain);
        if (\is_null($name)) {
            return $default;
        }
        if (\is_null($item)) {
            return self::$domain[$name] ?? [];
        }
        $data = self::$domain[$name][\strtolower($item)] ?? null;
        if (\is_null($key)) {
            return $data;
        }
        if (\is_null($data)) {
            return $default;
        }

        return Arr::get($key, $data, $default);
    }
 
    public static function domainSet(string $domain, string $item, array $data)
    {
        self::$domain[$domain][\strtolower($item)] = $data;
    }

    public static function vendorMatch(string $vendor, string $item, array $keys, $default = null, string &$_key = null)
    {
        $data = self::$vendor[$vendor][\strtolower($item)] ?? [];

        return Arr::match(\array_map('strtoupper', $keys), $data, $default, $_key);
    }

    public static function vendorGet(string $vendor = null, string $item = null, string $key = null, $default = null)
    {
        if (\is_null($vendor)) {
            return self::$vendor;
        }
        if (\is_null($item)) {
            return self::$vendor[$vendor] ?? [];
        }
        $data = self::$vendor[$vendor][\strtolower($item)] ?? null;
        if (\is_null($key)) {
            return $data;
        }
        if (\is_null($data)) {
            return $default;
        }

        return Arr::get($key, $data, $default);
    }

    public static function vendorSet(string $vendor, string $item, array $data)
    {
        self::$vendor[$vendor][\strtolower($item)] = $data;
    }
}
