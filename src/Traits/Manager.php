<?php

declare(strict_types=1);

namespace DOF\Traits;

use Closure;
use DOF\ENV;
use DOF\Convention;
use DOF\Exceptor\ManagerExceptor;
use DOF\Util\FS;
use DOF\Util\IS;
use DOF\Util\Arr;
use DOF\Util\Format;
use DOF\Util\Reflect;
use DOF\Util\Exceptor;
use DOF\Util\Annotation;

// Manager is a stateless and compile-cachable util to handle annotations related classes
trait Manager
{
    use \DOF\Traits\CompileCache;

    private static $list = [
        Convention::SRC_SYSTEM => [],
        Convention::SRC_VENDOR => [],
        Convention::SRC_DOMAIN => [],
    ];

    protected static $data = [];

    private static $system = [];
    private static $vendor = [];
    private static $domain = [];

    // override this method to custom paths of system, vendor and domain
    abstract public static function init();

    final public static function reset()
    {
        self::$list = [
            Convention::SRC_SYSTEM => [],
            Convention::SRC_VENDOR => [],
            Convention::SRC_DOMAIN => [],
        ];
        self::$data = [];
        self::$system = [];
        self::$vendor = [];
        self::$domain = [];
    }

    final public static function load()
    {
        if (\is_file($cache = self::formatCompileFile())) {
            list(self::$data, self::$system, self::$vendor, self::$domain) = Arr::load($cache);
            return;
        }

        self::compile(ENV::systemMatch([
            \join('_', [Format::c2u(Arr::last(static::class, '\\'), CASE_UPPER), 'COMPILE_CACHE']),
            'MANAGER_COMPILE_CACHE'
        ], false));
    }

    final public static function compile(bool $cache = false)
    {
        self::reset();

        static::init();

        foreach (self::$list as $src => $item) {
            Annotation::parseMixed($item, function ($annotations) use ($src) {
                list($ofClass, $ofProperties, $ofMethods) = $annotations;
                static::assemble($ofClass, $ofProperties, $ofMethods, $src);
            }, static::class);
        }

        if ($cache) {
            Arr::save([self::$data, self::$system, self::$vendor, self::$domain], self::formatCompileFile());
        }
    }
    
    abstract protected static function assemble(array $ofClass, array $ofProperties, array $ofMethods, string $type);

    /**
     * Valid src item before adding it into list
     */
    private static function addItem($item, Closure $callback)
    {
        if (\is_string($item) && (IS::namespace($item) || \is_file($item) || \is_dir($item))) {
            $callback($item);
            return;
        }

        if (\is_array($item)) {
            foreach ($item as $_item) {
                self::addItem($_item, $callback);
            }
            return;
        }

        throw new ManagerExceptor('INVALID_SRC_ITEM', \compact('item'));
    }

    /**
     * Add commands source into manager list from system defaults
     *
     * @param mixed $list namespace, file, folder where stores command classes
     */
    final public static function addSystem($item)
    {
        self::addItem($item, function ($item) {
            self::$list[Convention::SRC_SYSTEM][] = $item;
        });
    }

    final public static function getSystem()
    {
        return self::$system;
    }

    final public static function vendor(string $root, string $bootDir, string $bootFile)
    {
        $bootDir = FS::path($root, $bootDir);
        if (\is_dir($bootDir)) {
            FS::ll($bootDir, function ($vendors, $dir) use ($bootFile) {
                foreach ($vendors as $vendor) {
                    $_vendor = FS::path($dir, $vendor);
                    if (! \is_dir($_vendor)) {
                        continue;
                    }
                    FS::ll($_vendor, function ($packages, $dir) use ($vendor, $bootFile) {
                        foreach ($packages as $package) {
                            $booter = FS::path($dir, $package, $bootFile);
                            if (!\is_file($booter)) {
                                continue;
                            }
                            if ($src = Arr::load($booter, false)) {
                                self::addVendor(\join('/', [$vendor, $package]), $src);
                            }
                        }
                    });
                }
            });
        }
    }

    /**
     * Add commands source into manager list from DOF vendor packages
     *
     * @param mixed $list namespace, file, folder where stores command classes
     */
    final public static function addVendor(string $vendor, $item)
    {
        self::addItem($item, function ($item) use ($vendor) {
            self::$list[Convention::SRC_VENDOR][$vendor] = $item;
        });
    }

    public static function getVendor()
    {
        return self::$vendor;
    }

    /**
     * Add commands source into manager list from DOF domains
     *
     * @param mixed $list namespace, file, folder where stores command classes
     */
    public static function addDomain(string $domain, $item)
    {
        self::addItem($item, function ($item) use ($domain) {
            self::$list[Convention::SRC_DOMAIN][$domain] = $item;
        });
    }

    public static function getDomain()
    {
        return self::$domain;
    }

    public static function getAll()
    {
        return [self::$data, self::$system, self::$vendor, self::$domain];
    }

    public static function getList()
    {
        return self::$list;
    }

    public static function getData()
    {
        return self::$data;
    }

    public static function list()
    {
        return self::$list;
    }

    public static function get(string $key)
    {
        return self::$data[$key] ?? null;
    }
}
