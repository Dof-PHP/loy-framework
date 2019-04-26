<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\Container;

abstract class Service
{
    /** @var mixed: Service executed result data */
    protected $__result;

    /** @var bool: Whether this service's execute() method been called yet */
    protected $__executed = false;

    /** @var array: A config map for custom exception status */
    protected $__status = [];

    final public function exec()
    {
        $this->__result = $this->execute();

        $this->__executed = true;

        return $this;
    }

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

    final public function exception(string $message, array $context = [])
    {
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

    final public function __isExecuted() : bool
    {
        return $this->__executed;
    }

    final public static function __callStatic(string $method, array $argvs = [])
    {
        return call_user_func_array([self::init(), $method], $argvs);
    }
}
