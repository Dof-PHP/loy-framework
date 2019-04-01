<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

class Assembler
{
    /** @var mixed{array|object} */
    protected $origin;

    protected $compatibles = [];
    protected $converters  = [];

    public function __construct($origin = null)
    {
        $this->origin = $origin;
    }

    public function match(string $name, array $params = [])
    {
        $isObject = is_object($this->origin);
        $isArray  = is_array($this->origin);
        if ((! $isObject) && (! $isArray)) {
            return null;
        }

        $value = null;
        if ($isObject) {
            $value = $this->origin->{$name} ?? null;
        } elseif ($isArray) {
            $value = $this->origin[$name] ?? null;
        }

        if (is_null($value)) {
            $name = $this->compatibles[$name] ?? null;
            if (! $name) {
                return null;
            }
            if ($isObject) {
                $value = $this->origin->{$name} ?? null;
            }
            if ($isArray) {
                $value = $this->origin[$name] ?? null;
            }
        }

        $converter = $this->converters[$name] ?? null;
        if ($converter) {
            if (! method_exists($this, $converter)) {
                exception('FieldConvertNotExists', compact('converter'));
            }
            $value = $this->{$converter}($value, $params);
        }

        return $value;
    }

    /**
     * Setter for origin
     *
     * @param mixed $origin
     * @return Assembler
     */
    public function setOrigin($origin)
    {
        $this->origin = $origin;
    
        return $this;
    }
}
