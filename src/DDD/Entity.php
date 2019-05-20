<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

abstract class Entity
{
    /**
     * @Title(Primary Key)
     * @Type(Uint)
     */
    protected $id;

    final public static function init() : Entity
    {
        return new static;
    }

    public function onCreated()
    {
    }

    public function onUpdated()
    {
    }

    public function onDeleted()
    {
    }

    final public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    final public function getId() : ?int
    {
        return $this->id ?? null;
    }

    final public function get(string $attr)
    {
        if (property_exists($this, $attr)) {
            $val = $this->{$attr} ?? null;
            if (! is_null($val)) {
                return $val;
            }
            $getter = 'get'.ucfirst($attr);
            if (method_exists($this, $getter)) {
                return $this->{$getter}();
            }
        }
    }

    final public function set(string $attr, $val)
    {
        if (property_exists($this, $attr)) {
            $setter = 'set'.ucfirst($attr);
            if (method_exists($this, $setter)) {
                $this->{$setter}($val);
            } else {
                $this->{$attr} = $val;
            }
        }

        return $this;
    }

    final public function __get(string $attr)
    {
        return $this->get($attr);
    }

    final public function __set(string $attr, $val)
    {
        $this->set($attr, $val);
    }

    final public function __call(string $method, array $params = [])
    {
        if (0 === strpos($method, 'get')) {
            if ('get' !== $method) {
                $attr = lcfirst(substr($method, 3));
                return $this->get($attr);
            }
        }

        if (0 === strpos($method, 'set')) {
            if ('set' !== $method) {
                $attr = lcfirst(substr($method, 3));

                return $this->set($attr, ($params[0] ?? null));
            }
        }
    }

    final public function __toArray()
    {
        return (array) get_object_vars($this);
    }

    final public function toArray()
    {
        return $this->__toArray();
    }
}
