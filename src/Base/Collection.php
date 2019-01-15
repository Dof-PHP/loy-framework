<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

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
    private $count   = -1;
    private $pointer = 0;

    public function __construct(array $data, $origin = null)
    {
        $this->origin = $origin;
        $this->data   = $data;
        $this->keys   = array_keys($data);
    }

    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, $value)
    {
        if ($key && is_string($key)) {
            $this->data[$key] = $value;
        } else {
            $this->data[] = $value;
        }
    }

    public function getOrigin()
    {
        return $this->origin;
    }

    public function __call(string $method, array $argvs)
    {
        if (is_object($this->origin) && method_exists($this->origin, $method)) {
            return $this->origin->{$name}($argvs);
        }

        return $this->__get($method);
    }

    public function __get(string $key)
    {
        return $this->get($key);
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
        return (-1 !== $this->count) ? $this->count : count($this->data);
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
