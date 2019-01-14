<?php

declare(strict_types=1);

namespace Loy\Framework\Core;

class ApplicationService
{
    protected $__status = 0;
    protected $__error  = null;
    protected $__data   = null;

    public function __toArray()
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

    public function __getError()
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
