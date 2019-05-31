<?php

declare(strict_types=1);

namespace Dof\Framework;

use Throwable;
use Iterator;
use ArrayAccess;
use Countable;
use JsonSerializable;

class Collection implements
    Iterator,
    ArrayAccess,
    Countable,
    JsonSerializable
{
    private $origin = null;
    private $data    = [];
    private $keys    = [];
    private $count   = 0;
    private $pointer = 0;

    public function __construct(array $data = [], $origin = null)
    {
        $this->origin = $origin;
        $this->data   = $data;
        $this->keys   = array_keys($data);
    }

    public function getKeys() : array
    {
        return $this->keys;
    }

    public function getData() : array
    {
        return $this->data;
    }

    public function has(string $key) : bool
    {
        return in_array($key, $this->keys);
    }

    public function get($key, $default = null, array $rules = null)
    {
        $val = null;
        if (is_scalar($key)) {
            $val = $this->data[(string) $key] ?? null;
        }

        if (is_null($val) && $this->origin && method_exists($this->origin, '__collectionGet')) {
            // $val = call_user_func_array([$this->origin, '__collectionGet'], [$key, $this]);
            $val = is_object($this->origin)
                ? $this->origin->__collectionGet($key, $this)
                : $this->origin::__collectionGet($key, $this);
        }

        $val = is_null($val) ? $default : $val;

        if (! $rules) {
            return $val;
        }

        $validator = validate([$key => $val], [$key => $rules]);
        if (($fails = $validator->getFails()) && ($fail = $fails->first())) {
            $context = (array) $fail->value;

            exception($fail->key, $context);
        }

        return $validator->getResult()[$key] ?? null;
    }

    public function set($key = null, $value = null)
    {
        if (! $key) {
            return $this;
        }

        if ($key && is_string($key)) {
            $this->data[$key] = $value;
        } else {
            $this->data[] = $value;
        }

        return $this;
    }

    public function getOrigin()
    {
        return $this->origin;
    }

    public function __call(string $method, array $argvs)
    {
        if (method_exists($this->origin, $method)) {
            return call_user_func_array([$this->origin, $method], $argvs);
        }

        return $this->__get($method);
    }

    public function __get(string $key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        return $this->set($key, $value);
    }
    
    public function last()
    {
        $key = $this->keys[$this->count - 1] ?? false;

        return $key ? $this->get($key) : null;
    }

    public function first()
    {
        $key = $this->keys[0] ?? false;

        return $key ? new Collection(['key' => $key, 'value' => $this->get($key)]) : null;
    }

    public function current()
    {
        return $this->data[$this->keys[$this->pointer]];
    }
    
    public function key()
    {
        return $this->keys[$this->pointer];
    }
    
    public function next()
    {
        ++$this->pointer;
    }
    
    public function rewind()
    {
        $this->pointer = 0;
    }
    
    public function valid()
    {
        return (
            isset($this->keys[$this->pointer])
            && isset($this->data[$this->keys[$this->pointer]])
        );
    }
    
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }
    
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }
    
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }
    
    public function offsetUnset($offset)
    {
        if (isset($this->data[$offset])) {
            unset($this->data[$offset]);
        }
    }
    
    public function count()
    {
        return count($this->data);
    }
    
    public function __toArray()
    {
        return $this->toArray();
    }

    public function toArray()
    {
        return $this->data;
    }
    
    public function __toString()
    {
        return $this->toString();
    }
    
    public function toString()
    {
        return enjson($this->data);
    }
    
    public function jsonSerialize()
    {
        return $this->data;
    }
}
