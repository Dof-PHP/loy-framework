<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

use Loy\Framework\Facade\Assembler as AssemblerFacade;

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
        if ((! is_object($this->origin)) && (! is_array($this->origin))) {
            return null;
        }

        $key = $name;
        $val = AssemblerFacade::matchValue($key, $this->origin);
        if (is_null($val)) {
            $key = strtolower($name);
            $val = AssemblerFacade::matchValue($key, $this->origin);
            if (is_null($val)) {
                $key = strtoupper($name);
                $val = AssemblerFacade::matchValue($key, $this->origin);
            }
        }
        if (is_null($val)) {
            $key = $this->compatibles[$name] ?? null;
            // If key no exists even in compatibles setting
            // We tried non-standard field name the last two times: all-lowercase and ALL-UPPERCASE
            if (is_null($key)) {
                $key = $this->compatibles[strtolower($name)] ?? null;
                if (is_null($key)) {
                    $key = $this->compatibles[strtoupper($name)] ?? null;
                    if (is_null($key)) {
                        return null;
                    }
                }
            }
        }

        $val = AssemblerFacade::matchValue($key, $this->origin);
        $converter = $this->converters[$key] ?? null;
        if ($converter) {
            if (! method_exists($this, $converter)) {
                exception('FieldConvertNotExists', compact('converter'));
            }
            $val = $this->{$converter}($val, $params);
        }

        return $val;
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
