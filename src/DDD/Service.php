<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Throwable;
use Dof\Framework\Container;

abstract class Service
{
    const EXCEPTION_NAME = 'DofServiceException';

    /** @var array: A config map for custom exception */
    protected $__errors = [];

    abstract public function execute();

    final public function __status(array $map)
    {
        $this->__status = array_merge($this->__status, $map);

        return $this;
    }

    final public function error(array $error, int $status)
    {
        $code = $error[0] ?? -1;
        $info = $error[1] ?? -1;
        $this->__errors[$info] = [$code, $status];

        return $this;
    }

    final public function exception(string $message, array $context = [], Throwable $previous = null)
    {
        $context = parse_throwable($previous, $context);
        $context['__errors'] = $this->__errors[$message] ?? null;

        exception(self::EXCEPTION_NAME, compact('message', 'context'));
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
