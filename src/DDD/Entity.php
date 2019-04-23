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

    /**
     * Update entity self
     */
    final public function save()
    {
        // Callback onSaved
    }

    /**
     * Delete entity self
     */
    final public function delete()
    {
        // Callback onDeleted
    }

    public function onSaved()
    {
    }

    public function onDeleted()
    {
    }

    public function onUpdated()
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

    final public function __get(string $attr)
    {
        if (property_exists($this, $attr)) {
            return $this->{$attr};
        }
    }

    final public function __set(string $attr, $value)
    {
        if (property_exists($this, $attr)) {
            $this->{$attr} = $value;
        }
    }

    final public function __call(string $method, array $params = [])
    {
        if (0 === strpos($method, 'get')) {
            if ('get' !== $method) {
                $attr = lcfirst(substr($method, 3));
                if (property_exists($this, $attr)) {
                    return $this->{$attr};
                }
            }
        }

        if (0 === strpos($method, 'set')) {
            if ('set' !== $method) {
                $attr = lcfirst(substr($method, 3));
                if (property_exists($this, $attr)) {
                    $this->{$attr} = $value[0] ?? null;

                    return $this;
                }
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
