<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Throwable;
use Dof\Framework\Container;

abstract class Service
{
    /** @var array: A config map for custom exception status */
    protected $__status = [];

    abstract public function execute();

    final public function __status(array $map)
    {
        $this->__status = array_merge($this->__status, $map);

        return $this;
    }

    final public function status(string $message, int $status)
    {
        $this->__status[$message] = $status;

        return $this;
    }

    final public function exception(string $message, array $context = [], Throwable $previous = null)
    {
        $context = parse_throwable($previous, $context);
        $context['__status'] = $this->__status[$message] ?? null;

        exception('DofServiceException', compact('message', 'context'));
    }

    final public static function init()
    {
        return Container::di(static::class);
    }

    final public function toString() : string
    {
        return $this->__toString();
    }

    final public function __toString() : string
    {
        return enjson($this->__toArray());
    }

    final public function toArray() : array
    {
        return $this->__toArray();
    }

    final public function __toArray() : array
    {
        return get_object_vars($this);
    }

    final public static function __callStatic(string $method, array $argvs = [])
    {
        return call_user_func_array([self::init(), $method], $argvs);
    }
}
