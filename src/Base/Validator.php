<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\TypeHint;
use Loy\Framework\Base\Exception\ValidatorNotFoundException;
use Loy\Framework\Base\Exception\ValidationFailureException;
use Loy\Framework\Base\Exception\BadValidatorRuleException;
use Loy\Framework\Base\Exception\TypeHintConvertException;

class Validator
{
    protected $data = [];
    protected $rule = [];

    public function __construct(array $data = [], array $rule = [])
    {
        $this->data = $data;
        $this->rule = $rule;
    }

    public function validate(array $data = null, array $rule = null, array &$result = null)
    {
        $data   = is_null($data) ? $this->data : $data;
        $rule   = is_null($rule) ? $this->rule : $rule;
        $result = $data;
        foreach ($rule as $key => $rules) {
            if (! is_string($key)) {
                throw new BadValidatorRuleException('Non-String Key: '.stringify($key));
            }
            if (! is_array($rules)) {
                if (! is_string($rules)) {
                    throw new BadValidatorRuleException('Non-Arrayable Value: '.stringify($key));
                }

                $rules = array_trim(explode('|', $rules));
            }
            if ((! in_array('need', $rules)) && (! array_key_exists($key, $data))) {
                if (array_key_exists('default', $rules)) {
                    $result[$key] = $rules['default'] ?? null;
                }
                continue;
            }
            $val = $data[$key] ?? null;
            foreach ($rules as $_key => $_rule) {
                $rule  = is_int($_key)    ? $_rule : $_key;
                $error = is_string($_key) ? $_rule : null;
                $error = $error ?: $_rule;
                $arr   = array_trim(explode(':', $rule));
                $rule  = trim($arr[0] ?? '');
                if (! $rule) {
                    continue;
                }
                $validator = 'validate'.ucfirst(strtolower($rule));
                if (! method_exists($this, $validator)) {
                    throw new ValidatorNotFoundException($rule);
                }
                $params = ($rule === 'default') ? [$_rule] : array_trim(explode(',', $arr[1] ?? ''));
                try {
                    $res = $this->{$validator}($val, $data, $key, $params);
                } catch (TypeHintConvertException $e) {
                    throw new ValidationFailureException("{$error} ({$key})");
                }
                $result[$key] = $val;
                $_val = string_literal($val);
                if (is_null($res)) {
                    break;
                }
                if (is_string($res)) {
                    throw new BadValidatorRuleException("{$_rule} ({$res} => {$_val})");
                }
                if (false === $res) {
                    throw new ValidationFailureException("{$error} ({$key} => {$_val})");
                }
                if (true === $res) {
                    continue;
                }
            }
        }

        return true;
    }

    public function validateMax()
    {
        $max = ($params[0] ?? false);
        if (false === $max) {
            return 'Missing Max Value';
        }
        if (! TypeHint::isInt($max)) {
            return 'Bad Max Value';
        }
        $max = TypeHint::convertToInt($max);
        if (is_int($value)) {
            return $value <= $max;
        }
        if (TypeHint::isString($value)) {
            $value = TypeHint::convertToString($value);
            return mb_strlen($value) <= $max;
        }

        return false;
    }

    public function validateMin(&$value, array $data, string $key, array $params = [])
    {
        $min = ($params[0] ?? false);
        if (false === $min) {
            return 'Missing Min Value';
        }
        if (! TypeHint::isInt($min)) {
            return 'Bad Min Value';
        }
        $min = TypeHint::convertToInt($min);
        if (is_int($value)) {
            return $value >= $min;
        }
        if (TypeHint::isString($value)) {
            $value = TypeHint::convertToString($value);
            return mb_strlen($value) >= $min;
        }

        return false;
    }

    public function validateInt(&$value, array $data, string $key, array $params = [])
    {
        if (TypeHint::isInt($value)) {
            $value = TypeHint::convertToInt($value);
            return true;
        }

        return false;
    }

    public function validateString(&$value, array $data, string $key, array $params = [])
    {
        if (TypeHint::isString($value)) {
            $value = TypeHint::convertToString($value);
            return true;
        }

        return false;
    }

    public function validateNeedifhas(&$value, array $data, string $key, array $params = [])
    {
        if (! $params) {
            return 'Missing Comparators';
        }

        foreach ($params as $key) {
            if ($data[$key] ?? false) {
                return true;
            }
        }

        return null;
    }

    public function validateNeedifno(&$value, array $data, string $key, array $params = [])
    {
        if (! $params) {
            return 'Missing Comparators';
        }

        foreach ($params as $key) {
            if ($data[$key] ?? false) {
                return null;
            }
        }

        return true;
    }

    public function validateNeed(&$value, array $data, string $key, array $params = [])
    {
        return array_key_exists($key, $data);
    }

    public function validateDefault(&$value, array $data, string $key, array $params = [])
    {
        $value = $params[0] ?? null;

        return true;
    }

    public function validateHost(&$value, array $data, string $key, array $params = [])
    {
        return (false
            || (false !== filter_var($value, FILTER_VALIDATE_DOMAIN))
            || (false !== filter_var($value, FILTER_VALIDATE_IP))
        );
    }

    public function validateIp(&$value, array $data, string $key, array $params = [])
    {
        return false !== filter_var($value, FILTER_VALIDATE_IP);
    }

    public function validateEmail(&$value, array $data, string $key, array $params = [])
    {
        return false !== filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public static function __callStatic(string $method, array $params)
    {
        return call_user_func_array([(new static), $method], $params);
    }

    public static function execute(array $data = [], array $rule = [], array &$result = [])
    {
        return (new static)->validate($data, $rule, $result);
    }
}
