<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Traits;

use Dof\Framework\Container;

trait DI
{
    /**
     * @Title(Singleton instance pool)
     * @Type(Array)
     * @NoDiff(1)
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

    final public function di(string $namespace, bool $single = true)
    {
        return $single ? $this->single($namespace) : Container::di($namespace);
    }

    final public static function new()
    {
        return Container::di(static::class);
    }
}
