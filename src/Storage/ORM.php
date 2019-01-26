<?php

declare(strict_types=1);

namespace Loy\Framework\Storage;

/**
 * The ORM is an implementation detail of the Repository.
 * The ORM just makes it easy to access the db tables in an OOP friendly way. That's it.
 */
class ORM
{
    private $__dynamicProxyNamespace;

    public function __construct(int $id = null)
    {
        if ($id) {
            // TODO: find in database and set items in instance
        }
    }

    public function find(int $id)
    {
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
