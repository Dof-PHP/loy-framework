<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

use Loy\Framework\Container;

abstract class ApplicationService
{
    protected $__code = 200;
    protected $__info;
    protected $__data;
    protected $__meta;
    protected $__extra;

    public function exec()
    {
        $this->__data = $this->execute();

        return $this;
    }

    abstract public function execute();

    public function toArray() : array
    {
        return $this->__toArray();
    }
    
    public function __toArray() : array
    {
        return [
            'code'  => $this->__code,
            'info'  => $this->__info,
            'data'  => $this->__data,
            'meta'  => $this->__meta,
            'extra' => $this->__extra,
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

    public function isSuccess(int $success = null) : bool
    {
        $code = $success ?: 200;

        return $this->__code === $code;
    }

    public static function init()
    {
        return Container::di(static::class);
    }

    public static function __callStatic(string $method, array $argvs = [])
    {
        $service = Container::di(static::class);

        return call_user_func_array([$service, $method], $argvs);
    }
}
