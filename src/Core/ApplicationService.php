<?php

declare(strict_types=1);

namespace Loy\Framework\Core;

abstract class ApplicationService
{
    protected $__status = 0;
    protected $__error  = null;
    protected $__data   = null;

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
            'status' => $this->__status,
            'error'  => $this->__error,
            'data'   => $this->__data,
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

    protected function __setError(string $error)
    {
        $this->__error = $error;

        return $this;
    }

    public function __getError() : string
    {
        return $this->__error;
    }

    protected function __setStatus(int $status)
    {
        $this->__status = $status;

        return $this;
    }

    public function __getStatus()
    {
        return $this->__status;
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
