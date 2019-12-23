<?php

declare(strict_types=1);

namespace DOF;

use Throwable;
use Closure;
use DOF\Util\FS;
use DOF\Util\Str;
use DOF\Exceptor\WritePermissionDenied;
use DOF\Exceptor\DOFInitExceptor;

final class DOF
{
    // MAJOR.MINOR.PATCH, see more: https://semver.org/
    const VERSION = '2.0.0-alpha';

    /** @var string: DOF project root */
    private static $root;

    public static function init(string $root)
    {
        self::$root = $root;

        if ((false === ($path = FS::mkdir($root, Convention::DIR_RUNTIME))) || (! \is_writable($path))) {
            throw new WritePermissionDenied(\compact('path'));
        }

        self::alias();

        DMN::load();
        ETC::load();
        INI::load();

        if ($timezone = ENV::systemGet('TIMEZONE')) {
            if ((! \is_string($timezone)) || (false === \date_default_timezone_set($timezone))) {
                throw new DOFInitExceptor('INVALID_TIMEZONE', \compact('timezone'));
            }
        }

        if (I18N::active() && ENV::systemGet('LOAD_I18N_ONBOOT', false)) {
            I18N::load();
        }

        ErrManager::load();

        if (\is_dir($boot = FS::path($root, Convention::DIR_BOOT, Convention::SRC_VENDOR))) {
            FS::ls(function ($vendors, $dir) {
                foreach ($vendors as $vendor) {
                    $path = FS::path($dir, $vendor);
                    if (! \is_dir($path)) {
                        continue;
                    }
                    FS::ls(function ($packages, $dir) {
                        foreach ($packages as $package) {
                            $booter = FS::path($dir, $package, Convention::FILE_BOOT);
                            if (\is_file($booter)) {
                                try {
                                    (function ($booter) {
                                        require_once $booter;
                                    })($booter);
                                } catch (Throwable $th) {
                                    throw new DOFInitExceptor('DOF_BOOT_ERROR', \compact('booter'), $th);
                                }
                            }
                        }
                    }, $path);
                }
            }, $boot);
        }

        if (ENV::systemGet('INIT_CONTAINER_ONBOOT', false)) {
            Container::init($root);
        }
    }
   
    /**
     * Alias stateless classes with static methods only into top-level namespace
     */
    public static function alias()
    {
        \class_alias(\DOF\DOF::class, 'DOF');
        \class_alias(\DOF\ETC::class, 'ETC');
        \class_alias(\DOF\ENV::class, 'ENV');
        \class_alias(\DOF\INI::class, 'INI');
        \class_alias(\DOF\DMN::class, 'DMN');
        \class_alias(\DOF\I18N::class, 'I18N');
        \class_alias(\DOF\Domain::class, 'Domain');
        \class_alias(\DOF\ErrManager::class, 'ErrManager');
        \class_alias(\DOF\Container::class, 'Container');
        \class_alias(\DOF\Convention::class, 'Convention');
        \class_alias(\DOF\Surrogate\Log::class, 'Log');
    }

    public static function root(bool $ofProject = true) : ?string
    {
        return $ofProject ? self::$root : \dirname(__DIR__);
    }

    // Get absolute file path in DOF project
    public static function path(...$dirs) : string
    {
        return FS::path(self::$root, $dirs);
    }

    // Get relative file path to DOF project
    public static function pathof(string $path) : string
    {
        return \join(FS::DS, Str::arr(Str::shift($path, self::$root), FS::DS));
    }
}
