<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

use Dof\Framework\Facade\Annotation;
use Dof\Framework\Facade\Request;
use Dof\Framework\Facade\Validator;

final class Wrapin
{
    const RESERVE_KEYS = [
        'COMPATIBLE' => 1,
        // 'WRAPIN'  => 1,
        'TITLE'   => 1,
        '__ext__' => 1,
    ];

    public static function get(string $namespace)
    {
        return Annotation::parseNamespace($namespace, __CLASS__)[1] ?? null;
    }

    /**
     * Apply a wrapin on request parameters
     *
     * @param string $wrapin: Wrapin class to apply
     * @param iterable|null $params: The origin data to be validated
     * @return Dof\Framework\Validator
     */
    public static function apply(string $wrapin, $params = null)
    {
        if (! class_exists($wrapin)) {
            exception('WrapperInNotExists', compact('wrapin'));
        }

        $wrapins = self::get($wrapin);

        return self::execute($wrapins, $wrapin, $params);
    }

    /**
     * Execute a wrapin check by given validator arguments and origin
     *
     * @param iterable $arguments: Validate rules list
     * @param string $origin: The origin class request a wrapin check
     * @param iterable|null $params: The origin data to be validated
     * @return Dof\Framework\Validator
     */
    public static function execute($arguments, string $origin, $params = null)
    {
        if (is_collection($arguments)) {
            $arguments = uncollect($arguments);
        } elseif (is_array($arguments)) {
        } else {
            exception('UnIterableWrapinArguments', compact('arguments'));
        }

        $data = $rules = $wrapins = [];
        foreach ($arguments as $key => $argument) {
            $argument = $argument['doc'] ?? [];
            if (! $argument) {
                continue;
            }
            $_key = null;    // The field name first find a non-null value in key list
            $keys = array_keys(($argument['COMPATIBLE'] ?? []));
            array_unshift($keys, $key);
            $val  = self::match($keys, $_key, $params);
            if (! is_null($val)) {
                $data[$key] = $val;
            }

            $ext = $argument['__ext__'] ?? [];
            unset($argument['__ext__']);
            foreach ($argument as $annotation => $value) {
                if (self::RESERVE_KEYS[$annotation] ?? false) {
                    continue;
                }
                if ($annotation === 'WRAPIN') {
                    if ($ext['WRAPIN']['list'] ?? false) {
                        if (is_iterable($val)) {
                            foreach ($val as $item) {
                                $wrapins[] = [$value => $item];
                            }
                        }
                    } else {
                        $wrapins[] = [$value => $val];
                    }
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

        $validator = Validator::setData($data)->setRules($rules)->execute();
        if ($validator->getFails()) {
            return $validator;
        }

        // Execute another wrappins from annotations
        if ($wrapins) {
            foreach ($wrapins as $__wrapin) {
                foreach ($__wrapin as $wrapin => $_data) {
                    if (! $_data) {
                        continue;
                    }

                    $_wrapin = get_annotation_ns($wrapin, $origin);
                    if (false === $_wrapin) {
                        exception('InvalidWrapinAnnotation', compact('wrapin'));
                    }

                    $validator = self::apply($_wrapin, $_data);
                    if (($fails = $validator->getFails()) && ($fail = $fails->first())) {
                        $fails   = $fails->toArray();
                        $context = $fail->value;
                        $context['wrapins'][] = $_wrapin;
                        $fails[$fail->key] = $context;

                        $validator->setFails(collect($fails, null, false));

                        return $validator;
                    }
                }
            }
        }

        return $validator;
    }

    public static function match(array $keys, string $_key = null, $data = null)
    {
        if (is_null($data)) {
            return Request::match($keys, $_key);
        }

        foreach ($keys as $key) {
            if (! is_scalar($key)) {
                continue;
            }
            $key = (string) $key;
            $val = $data[$key] ?? null;
            if (! is_null($val)) {
                $_key = $key;
                return $val;
            }
        }

        return null;
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
