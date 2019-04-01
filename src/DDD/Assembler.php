<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

class Assembler
{
    /** @var mixed{array|object} */
    private $origin;

    protected $compatibles = [];
    protected $convert = [];

    public function __construct($origin = null)
    {
        $this->origin = $origin;
    }

    public function match(string $name)
    {
        $value = null;

        if (is_object($result)) {
            $value = $result->{$name} ?? null;
            if (is_null($value)) {
                $name = $this->compatibles[$name] ?? null;
                if (! $name) {
                    return null;
                }
                $value = $result->{$name} ?? null;
            }
        } elseif (is_array($result)) {
            $value = $result[$name] ?? null;
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
