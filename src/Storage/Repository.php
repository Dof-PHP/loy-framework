<?php

declare(strict_types=1);

namespace Loy\Framework\Storage;

class Repository
{
    private $__dynamicProxyNamespace;

    public function findById(int $id)
    {
        dd($id, $this->__dynamicProxyNamespace);
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
