<?php

declare(strict_types=1);

namespace DOF\Traits;

use DOF\Container;

trait DI
{
    /**
     * @Title(Singleton instance pool)
     * @Type(Array)
     * @NoDiff(1)
     * @Annotation(0)
     */
    private $__singleton__ = [];

    final public function single(string $namespace)
    {
        $single = $this->__singleton__[$namespace] ?? null;
        if ($single) {
            return $single;
        }

        return $this->__singleton__[$namespace] = Container::di($namespace);
    }

    final public function di(string $namespace, bool $single = false)
    {
        return $single ? $this->single($namespace) : Container::di($namespace);
    }

    final public static function new()
    {
        return Container::di(static::class);
    }
}
