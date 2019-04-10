<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Facade\Annotation;
use Loy\Framework\Facade\Request;
use Loy\Framework\Facade\Validator;

final class Wrapin
{
    const RESERVE_KEYS = [
        'COMPATIBLE' => 1,
        'TITLE'      => 1,
        'WRAPIN'     => 1,
        '__ext__'    => 1,
    ];

    public static function get(string $namespace)
    {
        return Annotation::parseNamespace($namespace, __CLASS__)[1] ?? null;
    }

    /**
     * Apply a wrapin on request parameters
     *
     * @param string $wrapin: Wrapin class to apply
     * @return Loy\Framework\Validator
     */
    public static function apply(string $wrapin)
    {
        $wrapins = self::get($wrapin);
        $data = $rules = [];
        foreach ($wrapins as $key => $argument) {
            $argument = $argument['doc'] ?? [];
            if (! $argument) {
                continue;
            }
            $_key = null;    // The field name first find a non-null value in key list
            $keys = array_keys($argument['COMPATIBLE'] ?? []);
            array_unshift($keys, $key);
            $val  = Request::match($keys, $_key);
            if (! is_null($val)) {
                $data[$key] = $val;
            }

            $ext = $argument['__ext__'] ?? [];
            unset($argument['__ext__']);
            foreach ($argument as $annotation => $value) {
                if (self::RESERVE_KEYS[$annotation] ?? false) {
                    continue;
                }
                $rule = $annotation;
                if ($annotation === 'DEFAULT') {
                    $rules[$key][$annotation] = $value;
                    continue;
                }
                if ($annotation === 'VALIDATOR') {
                    $rule = $value;
                    $value = $ext[$annotation]['ext'] ?? null;
                }

                $errmsg = $ext[$annotation]['err'] ?? (array_keys($ext[$annotation] ?? [])[0] ?? null);
                if (is_null($errmsg)) {
                    $rules[$key][] = sprintf('%s:%s', $rule, $value);
                } else {
                    $errmsg = sprintf($errmsg, (($argument['TITLE'] ?? $_key) ?? ''), $value);
                    $rules[$key][sprintf('%s:%s', $rule, $value)] = $errmsg;
                }
            }
        }

        return Validator::setData($data)->setRules($rules)->execute();
    }

    public static function __annotationMultipleCompatible() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeCompatible()
    {
        return 'kv';
    }

    public static function __annotationFilterCompatible(string $compatibles) : array
    {
        return array_trim_from_string($compatibles, ',');
    }
}
