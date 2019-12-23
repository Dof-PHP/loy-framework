<?php

declare(strict_types=1);

namespace DOF;

use DOF\DOF;
use DOF\Convention;
use DOF\Traits\CompileCache;
use DOF\Util\IS;
use DOF\Util\FS;
use DOF\Util\Str;
use DOF\Util\Arr;
use DOF\Util\Format;
use DOF\Util\Reflect;
use DOF\Util\Exceptor;

final class DMN
{
    use CompileCache;

    /** @var array: KEY => DIR */
    private static $list = [];

    /** @var array: Meta data of domains: KEY => META */
    private static $meta = [];

    public static function load(bool $_cache = false)
    {
        if (\is_file($cache = self::formatCompileFile())) {
            list(self::$list, self::$meta) = Arr::load($cache);
            return;
        }

        self::init($_cache);
    }

    public static function init(bool $cache = false)
    {
        // reset
        self::$list = [];
        self::$meta = [];

        self::find(DOF::path(Convention::DIR_DOMAIN));

        if ($cache) {
            Arr::save([self::$list, self::$meta], self::formatCompileFile());
        }
    }

    /**
     * Find domains in given directory
     *
     * @param string $dir: Directory absolute path
     */
    private static function find(string $path)
    {
        if (! \is_dir($path)) {
            return;
        }

        FS::ls(function (array $list, string $dir) {
            $flag = FS::path($dir, Convention::FLAG_DOMAIN);
            if (\is_file($flag)) {
                $key = self::key($flag);

                self::$list[$key] = $dir;
                self::$meta[$key] = Arr::load($flag);
                if (IS::empty(self::$meta[$key]['no'] ?? null)) {
                    throw new Exceptor('DOMAIN_WITHOUT_NO', \compact('flag', 'key'));
                }

                // One domain CAN NOT contains children, domains are peers
                // So we abort scanning once we find domain flag in current directory
                return;
            }

            foreach ($list as $pathname) {
                $path = FS::path($dir, $pathname);
                if (\is_dir($path)) {
                    self::find($path);

                    // Ignore non files
                    continue;
                }
            }
        }, $path);
    }

    /**
     * Format domain name based on domain flag
     */
    public static function key(string $flag) : ?string
    {
        if (! \is_dir($src = DOF::path(Convention::DIR_DOMAIN))) {
            return null;
        }

        return (function ($dir) {
            return \join('.', \array_map(function ($item) {
                return Format::c2u($item);
            }, Str::arr($dir, FS::DS)));
        })(Str::shift(\dirname($flag), $src));
    }

    /**
     * Get domain name based on domain file and namespace
     */
    public static function name(string $origin) : ?string
    {
        if (isset(self::$list[$_origin = \strtolower($origin)])) {
            return $_origin;
        }
        $file = null;
        if (\is_file($origin) ||\is_dir($origin)) {
            $file = \realpath($origin);
        } elseif (IS::namespace($origin)) {
            $file = Reflect::getNamespaceFile($origin);
        }
        if ($file) {
            foreach (self::$list as $key => $dir) {
                if (Str::start(\strtolower($dir), \strtolower($file))) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function path(string $domain) : ?string
    {
        return self::$list[self::name($domain)] ?? null;
    }

    public static function list()
    {
        return self::$list;
    }

    public static function meta(string $domain = null, string $key = null, $default = null)
    {
        if (\is_null($domain)) {
            return self::$meta;
        }
        $name = DMN::name($domain);
        if (\is_null($name)) {
            return null;
        }
        if (\is_null($key)) {
            return self::$meta[$name] ?? [];
        }

        return Arr::get($key, (self::$meta[$name] ?? []), $default);
    }
}
