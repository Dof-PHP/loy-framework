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

    public function exec()
    {
        $this->__data = $this->execute();

        $this->__executed = true;

        return $this;
    }

    abstract public function execute();

    public static function init()
    {
        return Container::di(static::class);
    }

    public function toArray() : array
    {
        return $this->__toArray();
    }
    
    public function __toArray() : array
    {
        return [
            'code' => $this->__code,
            'info' => $this->__info,
            'data' => $this->__data,
            'meta' => $this->__meta,
            'more' => $this->__more,
        ];
    }

    protected function __setData($data)
    {
        $this->__data = $data;

        return $this;
    }

    public function __getData()
    {
        return $this->__data;
    }

    protected function __setInfo(string $info)
    {
        $this->__info = $info;

        return $this;
    }

    public function __getInfo() : string
    {
        return $this->__info;
    }

    protected function __setCode(int $code)
    {
        $this->__code = $code;

        return $this;
    }

    public function __getCode()
    {
        return $this->__code;
    }

    public function __isExecuted() : bool
    {
        return $this->__executed;
    }

    public function __isFail(int $success = null) : bool
    {
        return !$this->__isSuccess($success);
    }

    public function __isSuccess(int $success = null) : bool
    {
        $code = $success ?: 0;

        return $this->__code === $code;
    }

    public static function __callStatic(string $method, array $argvs = [])
    {
        return call_user_func_array([self::init(), $method], $argvs);
    }
}
