<?php

declare(strict_types=1);

namespace Loy\Framework\Storage;

use Closure;
use Loy\Framework\Base\Exception\MethodNotExistsException;

class Repository
{
    private $__dynamicProxy;
    private $__dynamicProxyNamespace;

    public function find(int $id)
    {
        dd($id, $this->__dynamicProxyNamespace);
    }

    public function findById()
    {
        return $this->__callProxyOrSelf('findById', func_get_args(), function (int $id) {
            dd($id);
        });
    }

    public function __callProxyOrSelf(string $method, array $params, Closure $callback)
    {
        $proxy = $this->__getDynamicProxy();
        if ($proxy && method_exists($proxy, $method)) {
            return $proxy->{$method}(...$params);
        }

        return $callback(...$params);
    }

    public function __call(string $method, array $argvs = [])
    {
        $proxy = $this->__getDynamicProxy();
        if (! $proxy) {
            throw new MethodNotExistsException(join('@', [__CLASS__, $method]));
        }
        if (! method_exists($proxy, $method)) {
            throw new MethodNotExistsException(join('@', [$this->__dynamicProxyNamespace, $method]));
        }

        return call_user_func_array([$proxy, $method], $argvs);
    }

    public function __getDynamicProxy()
    {
        if (! $this->__dynamicProxy) {
            if ($this->__dynamicProxyNamespace && class_exists($this->__dynamicProxyNamespace)) {
                return $this->__dynamicProxy = new $this->__dynamicProxyNamespace;
            }

            return null;
        }

        return $this->__dynamicProxy;
    }

    public function __getDynamicProxyNamespace()
    {
        return $this->__dynamicProxyNamespace;
    }

    public function __setDynamicProxyNamespace($namespace)
    {
        $this->__dynamicProxyNamespace = $namespace;

        return $this;
    }
}
