<?php

declare(strict_types=1);

namespace DOF\Traits;

use DOF\DOF;
use DOF\Convention;
use DOF\Exceptor\WritePermissionDenied;
use DOF\Util\FS;
use DOF\Util\Str;

trait CompileCache
{
    final public static function formatCompileFile(...$params) : string
    {
        $dir = DOF::path(Convention::DIR_RUNTIME, Convention::DIR_COMPILE);

        if (false === FS::mkdir($dir)) {
            throw new WritePermissionDenied('COMPILE_DIR_UNWRITABLE', \compact('dir'));
        }

        return FS::path($dir, \join('.', Str::arr(static::class, '\\')));
    }

    final public static function removeCompileFile(...$params)
    {
        FS::unlink(self::formatCompileFile(...$params));
    }
}
