<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\DDD\Assembler as AssembleObject;
use Dof\Framework\DDD\Service;
use Dof\Framework\Paginator;

class Assembler
{
    /**
     * Assemble a single result target to satisfy given fields and specific assembler
     *
     * @param mixed{array|object} $result
     * @param array $fields
     * @param mixed{string|object} $assembler
     */
    public static function assemble($result, array $fields, $assembler = null)
    {
        if (! $fields) {
            return null;
        }

        if ($result instanceof Service) {
            $result = $result->execute();
        }
        if ($result instanceof Paginator) {
            $data = [];
            $list = $result->getList();
            foreach ($list as $item) {
                $data[] = Assembler::assemble($item, $fields, $assembler);
            }

            return $data;
        }

        if (! $result) {
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
            $name  = (string) $name;
            $value = $assembler ? $assembler->match($name, $params) : self::matchValue($name, $result);
            if (is_null($value)) {
                ++$nulls;
                if ($nulls > $nullableLimit) {
                    break;
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

            $_assembler = null;
            if ($assembler) {
                $_assembler = $assembler->assemblers()[$name] ?? null;
            }

            $data[$name] = Assembler::assemble($_result, $ref, $_assembler);
        }

        return $data;
    }

    public static function matchValue(string $key, $result)
    {
        $val = null;

        if (is_object($result)) {
            $val = $result->{$key} ?? null;
        } elseif (is_array($result)) {
            $val = $result[$key] ?? null;
        }

        return $val;
    }

    private static function getNullableLimit() : int
    {
        return 20;
    }
}
