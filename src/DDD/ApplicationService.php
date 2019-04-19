<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\Container;

abstract class ApplicationService
{
    /** @var int: Service executed result code */
    protected $__code = 0;

    /** @var string: Service executed result message */
    protected $__info = 'ok';

    /** @var mixed: Service executed result data */
    protected $__data;

    /** @var array: Service executed result meta data */
    protected $__meta;

    /** @var array: Service executed result extra data */
    protected $__more;

    /** @var bool: Whether this service's execute() method been called yet */
    protected $__executed = false;

    final public function exec()
    {
        $this->__data = $this->execute();

        $this->__executed = true;

        return $this;
    }

    abstract public function execute();

    final public static function init()
    {
        return Container::di(static::class);
    }

    final public function toArray() : array
    {
        return $this->__toArray();
    }
    
    final public function __toArray() : array
    {
        return [
            'code' => $this->__code,
            'info' => $this->__info,
            'data' => $this->__data,
            'meta' => $this->__meta,
            'more' => $this->__more,
        ];
    }

    final protected function __setData($data)
    {
        $this->__data = $data;

        return $this;
    }

    final public function __getData()
    {
        return $this->__data;
    }

    final protected function __setInfo(string $info)
    {
        $this->__info = $info;

        return $this;
    }

    final public function __getInfo() : string
    {
        return $this->__info;
    }

    final protected function __setCode(int $code)
    {
        $this->__code = $code;

        return $this;
    }

    final public function __getCode()
    {
        return $this->__code;
    }

    final public function __isExecuted() : bool
    {
        return $this->__executed;
    }

    final public function __isFail(int $success = null) : bool
    {
        return !$this->__isSuccess($success);
    }

    final public function __isSuccess(int $success = null) : bool
    {
        $code = $success ?: 0;

        return $this->__code === $code;
    }

    final public static function __callStatic(string $method, array $argvs = [])
    {
        return call_user_func_array([self::init(), $method], $argvs);
    }
}
