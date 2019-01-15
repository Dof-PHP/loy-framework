<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

abstract class ApplicationService
{
    protected $__code = 0;
    protected $__info = null;
    protected $__data = null;

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
            'code' => $this->__code,
            'info' => $this->__info,
            'data' => $this->__data,
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

    public function isSuccess() : bool
    {
        return $this->__status === 0;
    }

    public static function init($data = null)
    {
        return new static($data);
    }

    public static function __callStatic(string $method, array $argvs = [])
    {
        $service = new static;

        return call_user_func_array([$service, $method], $argvs);
    }
}
