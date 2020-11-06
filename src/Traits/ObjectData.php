<?php

declare(strict_types=1);

namespace DOF\Traits;

use DOF\Container;
use DOF\Util\IS;
use DOF\Util\Str;
use DOF\Util\Arr;
use DOF\Util\XML;
use DOF\Util\JSON;
use DOF\Util\Annotation;

trait ObjectData
{
    public function __wakeup()
    {
        if (\method_exists($this, '__construct')) {
            $this->__construct(...Container::build(static::class, '__construct'));
        }
    }

    public function __sleep()
    {
        // https://www.php.net/manual/en/language.oop5.magic.php#object.sleep
        return \array_keys($this->__data__());
    }

    final public function __toString()
    {
        return \serialize($this);
    }

    final public function __toArray() : array
    {
        return \get_object_vars($this);
    }

    // Deep copy and reset all meta properties
    final public function __clone()
    {
        list(, $properties, ) = Annotation::getByNamespace(\get_class($this));
        foreach (\get_object_vars($this) as $key => $value) {
            if (\array_key_exists($key, $properties)) {
                $this->{$key} = \is_object($value) ? (clone $value) : $value;
            } else {
                $this->{$key} = (new static)->{$key} ?? null;    // reset meta properties as default
            }
        }
    }

    final public function __clone__()
    {
        // empty method for flag using
    }

    final public function __data__(object $object = null) : array
    {
        $object = $object ?? $this;

        if (IS::closure($object)) {
            return [];
        }

        $data = [];
        list(, $properties, ) = Annotation::getByNamespace(\get_class($object));

        // Warning: \get_object_vars can only get public/protected properties
        // \get_object_vars($this) -> pubilc & protected
        // \get_object_vars($object) -> pubilc
        // see: https://www.php.net/manual/en/function.get-object-vars
        foreach (\get_object_vars($object) as $key => $value) {
            if (\array_key_exists($key, $properties)) {
                $data[$key] = \is_object($value) ? $this->__data__($value) : $value;
            }
        }

        return $data;
    }

    final public function __toXml()
    {
        return XML::encode($this->__data__());
    }

    final public function __toJson()
    {
        return JSON::encode($this->__data__());
    }
}
