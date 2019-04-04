<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

use Loy\Framework\Facade\Assembler as AssemblerFacade;

class Assembler
{
    /** @var mixed{array|object} */
    protected $origin;

    /**
     * Compatibles are fields name which will be used to check when requested field not directly exists in origin
     * All key in $compatibles are case insensitive
     *
     * @var array
     */
    protected $compatibles = [];

    /**
     * Converters are value converters which will be used when found field in origin or compatible mappings
     *
     * Key of a converter map item is the field name exists exactly in origin
     * Value of a converter map item is the method name of current assembler class, and that method will accept field value and options as method parameters
     *
     * @var array
     */
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
