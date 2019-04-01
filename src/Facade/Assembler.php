<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Loy\Framework\DDD\Assembler as AssembleObject;

class Assembler
{
    public static function assemble($result, array $fields, $assembler = null)
    {
        if ((! $result) || (! $fields)) {
            return null;
        }
        if ($assembler) {
            if (is_string($assembler)) {
                if (! class_exists($assembler)) {
                    exception('AssemblerNotFound', compact('assembler'));
                }
                $assembler = new $assembler($result);
            } elseif (is_object($assembler) && ($assembler instanceof AssembleObject)) {
                $assembler->setOrigin($result);
            } else {
                exception('InvalidAssembler', compact('assembler'));
            }
        }

        $selfs = $fields['fields'] ?? [];
        $refs  = $fields['refs']   ?? [];
        $data  = [];
        $nulls = 0;
        $nullableLimit = self::getNullableLimit();
        foreach ($selfs as $name => $params) {
            $value = $assembler ? $assembler->match($name) : self::matchValue($name, $result);
            if (is_null($value)) {
                ++$nulls;
                if ($nulls > $nullableLimit) {
                    continue;
                }
            }

            $data[$name] = $value;
        }

        foreach ($refs as $name => $ref) {
            $_result = null;
            if (is_object($result)) {
                $_result = $result->{$name} ?? null;
            } elseif (is_array($result)) {
                $_result = $result[$name] ?? null;
            }

            $data[$name] = Assembler::assemble($_result, $ref);
        }

        return $data;
    }

    private static function matchValue(string $key, $result)
    {
        $value = null;

        if (is_object($result)) {
            $value = $result->{$name} ?? null;
        } elseif (is_array($result)) {
            $value = $result[$name] ?? null;
        }

        return $value;
    }

    private static function getNullableLimit() : int
    {
        return 20;
    }
}
