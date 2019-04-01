<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

class Entity
{
    public function getId() : ?int
    {
        return $this->id ?? null;
    }

    public function __get(string $attr)
    {
        if (property_exists($this, $attr)) {
            return $this->{$attr};
        }
    }

    public function __set(string $attr, $value)
    {
        if (property_exists($this, $attr)) {
            $this->{$attr} = $value;
        }
    }

    public function __call(string $method, array $params = [])
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
}
