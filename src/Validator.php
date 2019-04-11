<?php

declare(strict_types=1);

namespace Loy\Framework;

use Throwable;

class Validator
{
    const REQUIRE_RULES = [
        'need' => 1,
        'needifno'  => 1,
        'needifhas' => 1,
    ];

    /** @var array: A List of Collection object for failed validations (outer) */
    protected $fails;

    /** @var array: The validated result */
    protected $result;

    /** @var array: The data origin to be validated */
    protected $data = [];

    /** @var array: The rules used to validate given data */
    protected $rules = [];

    /** @var bool: Whether abort validation process when first rule run fails */
    protected $abortOnFail = true;

    public function execute()
    {
        foreach ($this->rules as $key => $rules) {
            // check if we need validate current parameters aginst rules first
            $_rules = array_keys($rules);
            $need   = !is_null($this->data[$key] ?? null);
            if (! $need) {
                foreach ($_rules as $_rule) {
                    if (Validator::REQUIRE_RULES[strtolower($_rule)] ?? false) {
                        $need = true;
                        break;
                    }
                }
            }
            if (! $need) {
                continue;
            }

            foreach ($rules as $rule => list($msg, $ext)) {
                $ext = ci_equal($rule, 'default') ? [$msg] : array_trim_from_string($ext, ',');
                $res = $this->validate($rule, $key, $ext);
                if (is_null($res)) {
                    break;
                }

                if (true !== $res) {
                    $val = $this->data[$key] ?? null;
                    $msg = sprintf($msg, $key, $val, ...$ext);
                    $this->addFail($msg, compact('key', 'val', 'ext'));
                    if ($this->abortOnFail) {
                        return $this;
                    }
                }
            }
        }

        return $this;
    }

    private function validate(string $rule, string $key, array $ext)
    {
        if (! $rule) {
            exception('EmptyValidatorRule', compact('key', 'rule'));
        }
        $validator = 'validate'.ucfirst(strtolower($rule));
        if (! method_exists($this, $validator)) {
            exception('ValidatorNotFound', compact('validator'));
        }

        return $this->{$validator}($key, ...$ext);
    }

    private function validateValidator(string $key, string $rule, array $ext = [])
    {
        return $this->validate($rule, $key, $ext);
    }

    private function validateMax(string $key, $max)
    {
        $value = $this->data[$key] ?? null;

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

    private function validateMin(string $key, $min)
    {
        $value = $this->data[$key] ?? null;

        if (! TypeHint::isInt($min)) {
            exception('MinValueIsNotInteger', compact('min'));
        }
        $min = TypeHint::convertToInt($min);
        if (TypeHint::isInt($value)) {
            $this->result[$key] = $value = TypeHint::convertToInt($value);

            return $value >= $min;
        }
        if (TypeHint::isString($value)) {
            $this->result[$key] = $value = TypeHint::convertToString($value);

            return mb_strlen($value) >= $min;
        }

        return false;
    }

    private function validateArray($key)
    {
        return is_array($this->data[$key] ?? null);
    }

    private function validateUint(string $key) : bool
    {
        $value = $this->data[$key] ?? null;

        if (! TypeHint::isInt($value)) {
            return false;
        }

        $this->result[$key] = $value = TypeHint::convertToInt($value);

        return $value > 0;
    }

    private function validateInt(string $key)
    {
        $value = $this->data[$key] ?? null;

        if (TypeHint::isInt($value)) {
            $this->result[$key] = TypeHint::convertToInt($value);
            return true;
        }

        return false;
    }

    private function validateString(string $key)
    {
        $value = $this->data[$key] ?? null;

        if (TypeHint::isString($value)) {
            $this->result[$key] = TypeHint::convertToString($value);
            return true;
        }

        return false;
    }

    private function validateNamespace($key)
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) && class_exists($value);
    }

    private function validateNeedifhas(string $key, string $has)
    {
        $value = $this->data[$key] ?? null;

        if (is_null($this->data[$has] ?? null)) {
            return null;
        }

        return (!is_null($value)) && ($value !== '');
    }

    private function validateNeedifno(string $key, string $no)
    {
        $value = $this->data[$key] ?? null;

        if (is_null($this->data[$no] ?? null)) {
            return (!is_null($value)) && ($value !== '');
        }

        return null;
    }

    private function validateNeed($key)
    {
        $value = $this->data[$key] ?? null;

        return !is_null($value) && ($value !== '');
    }

    private function validateCiin(string $key, ...$list)
    {
        $value = $this->data[$key] ?? null;

        if (! is_scalar($value)) {
            return false;
        }

        $value = strtolower((string) $value);

        return in_array($value, array_map(function ($item) {
            return strtolower((string) $item);
        }, $list));
    }

    private function validateIn(string $key, ...$list)
    {
        $value = $this->data[$key] ?? null;

        return in_array($value, $list);
    }

    private function validateDefault(string $key, $default)
    {
        $value = $this->data[$key] ?? null;

        $this->data[$key] = $this->result[$key] = (
            (is_null($value) || ('' === $value)) ? $default : $value
        );
        
        return true;
    }

    private function validateHost(string $key)
    {
        $value = $this->data[$key] ?? null;

        return (false
            || (false !== filter_var($value, FILTER_VALIDATE_DOMAIN))
            || (false !== filter_var($value, FILTER_VALIDATE_IP))
        );
    }

    private function validateIp(string $key)
    {
        $value = $this->data[$key] ?? null;

        return false !== filter_var($value, FILTER_VALIDATE_IP);
    }

    private function validateMobile(string $key, string $flag = 'cn')
    {
        $value = $this->data[$key] ?? null;

        if ((! $value) || (! is_scalar($value))) {
            return false;
        }

        $value = (string) $value;

        switch ($flag) {
            case 'cn':
            default:
                return 1 === preg_match('#^(\+86[\-\ ])?1\d{10}$#', $value);
        }

        return true;    // FIXME
    }

    private function validateEmail(string $key)
    {
        $value = $this->data[$key] ?? null;

        return false !== filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    private function addFail(string $fail, array $context = [])
    {
        if ($this->fails) {
            $this->fails->set($fail, $context);
        } else {
            $this->fails = collect([$fail => $context], null, false);
        }
    }

    private function check(array $rules)
    {
        $result = [];
        foreach ($rules as $key => $_rules) {
            $__rules = [];
            if (! is_array($_rules)) {
                if (! is_string($_rules)) {
                    exception('InvalidValidatorRules', compact('key', 'rules'));
                }

                parse_str($_rules, $__rules);
            } else {
                $__rules = $_rules;
            }

            $hasRequireRule = $hasDefaultRule = false;
            foreach ($__rules as $rule => $msg) {
                if ((! is_scalar($rule)) || (! is_scalar($msg))) {
                    exception('InvalidValidatorRule', compact('rule', 'msg'));
                }

                if (is_int($rule)) {
                    $rule = $msg;
                    $msg  = null;
                }

                $rarr = array_trim_from_string($rule, ':');
                $rule = $rarr[0] ?? null;
                if (! $rule) {
                    continue;
                }
                $ext  = $rarr[1] ?? '';
                $msg  = $msg ?: $this->getDefaultRuleMessage($rule, $key);

                if (Validator::REQUIRE_RULES[strtolower($rule)] ?? false) {
                    if ($hasRequireRule) {
                        exception('MultipleRequireRulesInSingleKey', [
                            'previous' => $hasRequireRule,
                            'conflict' => $rule,
                            'key' => $key,
                        ]);
                    }

                    $hasRequireRule = $rule;
                }
                if (ci_equal($rule, 'default')) {
                    $hasDefaultRule = $rule;
                }

                $result[$key][$rule] = [$msg, $ext];
            }

            if ($hasRequireRule) {
                $requireRule = $result[$key][$hasRequireRule];
                unset($result[$key][$hasRequireRule]);
                $result[$key] = array_merge([$hasRequireRule => $requireRule], $result[$key]);
            }
            if ($hasDefaultRule) {
                $defaultRule = $result[$key][$hasDefaultRule];
                unset($result[$key][$hasDefaultRule]);
                $result[$key] = array_merge([$hasDefaultRule => $defaultRule], $result[$key]);
            }
        }

        return $result;
    }

    public function getDefaultRuleMessage(string $rule) : string
    {
        $map = [
            'need' => 'RequireParameter:%s',
            'needifhas' => 'RequireParameterIfExists:%s',
            'needifno' => 'RequireParameterIfMissing:%s',
            'in' => 'ParameterNotIn:%s',
            'ciin' => 'ParameterNotCiin:%s',
        ];

        $rule  = strtolower($rule);
        $_rule = ucfirst($rule);

        return $map[$rule] ?? "Invalid{$_rule}:%s";
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
        $this->rules  = $this->check($rules);
        $this->errors = $this->fails = null;
    
        return $this;
    }

    /**
     * Setter for abortOnFail
     *
     * @param bool $abortOnF
     * @return Validator
     */
    public function setAbortOnF(bool $abortOnFail)
    {
        $this->abortOnFail = $abortOnFail;
    
        return $this;
    }

    /**
     * Setter for fails
     *
     * @param Loy\Framework\Collection $fails
     * @return Validator
     */
    public function setFails($fails)
    {
        $this->fails = $fails;
    
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
