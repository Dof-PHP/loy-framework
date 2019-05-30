<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\TypeHint;
use Dof\Framework\ModelManager;

/**
 * Data Model
 */
abstract class Model
{
    final public static function new()
    {
        return new static;
    }

    public static function init(array $data)
    {
        $model = static::class;
        $instance = new $model;
        $annotations = ModelManager::get($model);

        foreach ($data as $property => $val) {
            $attr = $annotations['properties'][$property] ?? null;
            if (! $attr) {
                continue;
            }
            $type = $attr['TYPE'] ?? null;
            if (! $type) {
                exception('MissingTypeInModelProperty', compact('property', 'model'));
            }

            $setter = 'set'.ucfirst($property);
            $instance->{$setter}(TypeHint::convert($val, $type));
        }

        return $instance;
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
