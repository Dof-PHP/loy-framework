<?php

declare(strict_types=1);

namespace Loy\Framework;

use Throwable;

class Validator
{
    /** @var array: A List of Collection object for failed validations (outer) */
    protected $fails;

    /** @var array: The validated result */
    protected $result;

    /** @var array: The data origin to be validated */
    protected $data = [];

    /** @var array: The rules used to validate given data */
    protected $rules = [];

    public function execute()
    {
        foreach ($this->rules as $key => $rules) {
            if (! is_array($rules)) {
                if (! is_string($rules)) {
                    exception('BadValidatorRules', [
                        'error' => 'Non-arrayable rules value',
                        'key'   => stringify($key)
                    ]);
                }

                $rules = array_trim_from_string($rules, '|');
            }

            $val = $this->data[$key] ?? null;
            foreach ($rules as $_key => $_rule) {
                $rule  = is_int($_key)    ? $_rule : $_key;
                $error = is_string($_key) ? $_rule : 'ValidationFailed';
                $rarr  = array_trim_from_string($rule, ':');
                $rule  = trim($rarr[0] ?? '');
                if (! $rule) {
                    continue;
                }
                $validator = 'validate'.ucfirst(strtolower($rule));
                if (! method_exists($this, $validator)) {
                    exception('ValidatorNotFound', compact('validator'));
                }
                $params = ci_equal($rule, 'default') ? [$_rule] : array_trim_from_string($rarr[1] ?? '', ',');
                $result = $this->{$validator}($val, $key, ...$params);
                if (is_null($result)) {
                    break;
                }
                if (true !== $result) {
                    $this->addFail((string) $error, [$key => $val, 'rule' => $rule]);
                    continue;
                }
            }
        }

        return $this;
    }

    private function validateValidator($value, string $key, string $validator)
    {
        $validator = 'validate'.ucfirst(strtolower($validator));
        pd($value, $key, $validator);

        // TODO
    }

    private function validateMax($value, string $key, $max)
    {
        if (! TypeHint::isInt($max)) {
            exception('MaxValueIsNotInteger', compact('max'));
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

    private function validateMin($value, string $key, $min)
    {
        if (! TypeHint::isInt($min)) {
            exception('MinValueIsNotInteger', compact('min'));
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

    private function validateArray($value)
    {
        return is_array($value);
    }

    private function validateUint($value) : bool
    {
        return TypeHint::isInt($value) && ($value > 0);
    }

    private function validateInt($value, string $key)
    {
        if (TypeHint::isInt($value)) {
            $this->result[$key] = TypeHint::convertToInt($value);
            return true;
        }

        return false;
    }

    private function validateString($value, string $key)
    {
        if (TypeHint::isString($value)) {
            $this->result[$key] = TypeHint::convertToString($value);
            return true;
        }

        return false;
    }

    private function validateNamespace($value)
    {
        return is_string($value) && class_exists($value);
    }

    private function validateNeedifhas($value, string $key, string $has)
    {
        if (is_null($this->data[$has] ?? null)) {
            return null;
        }

        return !is_null($value);
    }

    private function validateNeedifno($value, string $key, string $no)
    {
        if (is_null($this->data[$no] ?? null)) {
            return !is_null($value);
        }

        return null;
    }

    private function validateNeed($value)
    {
        return !is_null($value);
    }

    private function validateDefault($value, string $key, $default)
    {
        $this->result[$key] = is_null($value) ? $default : $value;

        return true;
    }

    private function validateHost($value)
    {
        return (false
            || (false !== filter_var($value, FILTER_VALIDATE_DOMAIN))
            || (false !== filter_var($value, FILTER_VALIDATE_IP))
        );
    }

    private function validateIp($value)
    {
        return false !== filter_var($value, FILTER_VALIDATE_IP);
    }

    private function validateEmail($value)
    {
        return false !== filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    private function addFail(string $fail, array $context = [])
    {
        if ($this->fails) {
            $this->fails[] = [$fail => $context];
        } else {
            $this->fails = collect([$fail => $context]);
        }

        return $this;
    }

    /**
     * Setter for data
     *
     * @param array $data
     * @return Validator
     */
    public function setData(array $data)
    {
        $this->data = $this->result = $data;
        $this->errors = $this->fails = null;
    
        return $this;
    }

    /**
     * Setter for rules
     *
     * @param array $rules
     * @return Validator
     */
    public function setRules(array $rules)
    {
        $this->rules = $rules;
        $this->errors = $this->fails = null;
    
        return $this;
    }

    /**
         * Getter for fails
         *
         * @return Loy\Framework\Collection|null
         */
    public function getFails()
    {
        return $this->fails;
    }

    /**
     * Getter for result
     *
     * @return array|null
     */
    public function getResult(): ?array
    {
        return $this->result;
    }
}
