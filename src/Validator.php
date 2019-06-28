<?php

declare(strict_types=1);

namespace Dof\Framework;

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
            $val = $this->data[$key] ?? null;
            $noneed = is_null($val) || ('' === $val) || (is_array($val) && empty($val));
            $need = !$noneed;
            if ($noneed) {
                $exists = array_key_exists($key, $this->data);
                foreach ($rules as $_rule => $_ext) {
                    // Typehint to defined type
                    if ($exists && ci_equal($_rule, 'TYPE') && ($type = ($_ext[1] ?? null))) {
                        if (! TypeHint::support($type)) {
                            exception('UnSupportedTypeHint', compact('key', 'val', 'type'));
                        }

                        try {
                            $this->result[$key] = TypeHint::convert($val, $type, true);
                        } catch (Throwable $e) {
                            exception('TypeHintEmptyValueFailed', compact('tpye', 'key', 'val'), $e);
                        }
                    }
                    if (self::REQUIRE_RULES[strtolower($_rule)] ?? false) {
                        $need = true;
                        // break;
                    }
                }
            }

            // If value is null or empty and no require rules on that value
            // Then we skip the next validateions
            if (! $need) {
                continue;
            }

            foreach ($rules as $rule => list($msg, $ext)) {
                $ext = ci_equal($rule, 'default') ? [$ext] : array_trim_from_string($ext, ',');
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

    private function validateType(string $type, string $rule, array $ext = [])
    {
        return $this->validate($rule, $type, $ext);
    }

    private function validateValidator(string $validator, string $rule, array $ext = [])
    {
        return $this->validate($rule, $key, $validator);
    }

    private function validateLength(string $key, $length)
    {
        $value = $this->data[$key] ?? null;
        if (! TypeHint::isString($value)) {
            return false;
        }

        return mb_strlen($value) === $length;
    }

    private function validateMax(string $key, $max)
    {
        $value = $this->data[$key] ?? null;

        if (! TypeHint::isInt($max)) {
            exception('MaxValueIsNotInteger', compact('max'));
        }
        $max = TypeHint::convertToInt($max);
        $type = $this->rules[$key]['TYPE'][1] ?? 'string';
        if (ciin($type, ['int', 'pint', 'uint', 'bint'])) {
            $this->result[$key] = $value = TypeHint::convertToInt($value);

            return $value <= $max;
        }
        if (ci_equal($type, 'string')) {
            $this->result[$key] = $value = TypeHint::convertToString($value);

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

        $type = $this->rules[$key]['TYPE'][1] ?? 'string';
        if (ciin($type, ['int', 'pint', 'uint', 'bint'])) {
            $this->result[$key] = $value = TypeHint::convertToInt($value);

            return $value >= $min;
        }
        if (ci_equal($type, 'string')) {
            $this->result[$key] = $value = TypeHint::convertToString($value);

            return mb_strlen($value) >= $min;
        }

        return false;
    }

    private function validateList($key)
    {
        return $this->validateListarray($key);
    }

    private function validateScalararray($key)
    {
        return $this->validateValuearray($key);
    }

    private function validateValuearray($key)
    {
        $list = $this->data[$key] ?? null;
        if (! $list) {
            return false;
        }
        if (! is_array($list)) {
            return false;
        }
        foreach ($list as $idx => $val) {
            if (! is_int($idx)) {
                return false;
            }
            if (! is_scalar($val)) {
                return false;
            }
        }

        return true;
    }

    private function validateObjectarray($key)
    {
        return $htis->validateAssocarray($key);
    }

    private function validateAssocarray($key)
    {
        $list = $this->data[$key] ?? null;
        if (! $list) {
            return false;
        }
        if (! is_array($list)) {
            return false;
        }

        foreach ($list as $key => $val) {
            if (! is_string($key)) {
                return false;
            }
        }

        return true;
    }

    private function validateIndexarray($key)
    {
        $list = $this->data[$key] ?? null;
        if (! $list) {
            return false;
        }
        if (! is_array($list)) {
            return false;
        }
        $keys = array_keys($list);
        foreach ($keys as $idx) {
            if (! TypeHint::isInt($idx)) {
                return false;
            }
        }

        return true;
    }

    private function validateListarray($key)
    {
        $list = $this->data[$key] ?? null;
        if (! $list) {
            return false;
        }
        if (! is_array($list)) {
            return false;
        }
        foreach ($list as $idx => $item) {
            if (! TypeHint::isInt($idx)) {
                return false;
            }
            if (! is_array($item)) {
                return false;
            }
        }

        return true;
    }

    private function validateIdarray($key)
    {
        $val = $this->data[$key] ?? null;
        if ((! $val) || (! is_array($val))) {
            return false;
        }

        $_val = id_array($val);
        if (count($_val) !== count($val)) {
            return false;
        }

        // Avoid emtpy array be collected
        $this->result[$key] = ($val === []) ? null : array_unique($val);

        return true;
    }

    private function validateIdlist($key)
    {
        $val = $this->data[$key] ?? null;
        if ((! $val) || (! is_string($val))) {
            return false;
        }

        $_val = id_list($val);
        if (count($_val) !== count($val)) {
            return false;
        }

        // Avoid emtpy array be collected
        $this->result[$key] = ($val === []) ? null : array_unique($val);

        return true;
    }

    private function validateArray($key)
    {
        return is_array($this->data[$key] ?? null);
    }

    private function validateNint(string $key) : bool
    {
        $value = $this->data[$key] ?? null;

        if (! TypeHint::isInt($value)) {
            return false;
        }

        $this->result[$key] = $value = TypeHint::convertToInt($value);

        return $value < 0;
    }

    private function validateBint(string $key) : bool
    {
        $value = $this->data[$key] ?? null;

        if (! TypeHint::isInt($value)) {
            return false;
        }

        $this->result[$key] = $value = TypeHint::convertToInt($value);

        return in_array($value, [0, 1]);
    }

    private function validatePint(string $key) : bool
    {
        $value = $this->data[$key] ?? null;

        if (! TypeHint::isInt($value)) {
            return false;
        }

        $this->result[$key] = $value = TypeHint::convertToInt($value);

        return $value > 0;
    }

    private function validateUint(string $key) : bool
    {
        $value = $this->data[$key] ?? null;

        if (! TypeHint::isInt($value)) {
            return false;
        }

        $this->result[$key] = $value = TypeHint::convertToInt($value);

        return $value >= 0;
    }

    private function validateBool(string $key)
    {
        return is_bool($this->data[$key] ?? null);
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

        return IS::namespace($value);
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

        return (! is_null($value)) && ($value !== '');
    }

    private function validateCiins(string $key, ...$list)
    {
        $value = $this->data[$key] ?? null;

        if (is_null($value)) {
            return false;
        }

        if (! is_array($value)) {
            return false;
        }

        return ciins($value, $list);
    }

    private function validateCiin(string $key, ...$list)
    {
        $value = $this->data[$key] ?? null;

        return ciin($value, $list);
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

    private function validateUrl(string $key)
    {
        $value = $this->data[$key] ?? null;

        return IS::url($value);
    }

    private function validateHost(string $key)
    {
        $value = $this->data[$key] ?? null;

        return IS::host($value);
    }

    private function validateIpv6(string $key)
    {
        $value = $this->data[$key] ?? null;

        return IS::ipv6($value);
    }

    private function validateIpv4(string $key)
    {
        $value = $this->data[$key] ?? null;

        return IS::ipv4($value);
    }

    private function validateIp(string $key)
    {
        $value = $this->data[$key] ?? null;

        return IS::ip($value);
    }

    private function validateMobile(string $key, string $flag = 'cn')
    {
        $value = $this->data[$key] ?? null;

        return IS::mobile($value, $flag);
    }

    private function validateEmail(string $key)
    {
        $value = $this->data[$key] ?? null;

        return IS::email($value);
    }

    private function validateDateFormat(string $key, string $format)
    {
        if (! $format) {
            return false;
        }

        $value = $this->data[$key] ?? null;
        if (! $value) {
            return false;
        }
        if (! is_string($value)) {
            return false;
        }

        return is_date_format($value, $format);
    }

    private function addFail(string $fail, array $context = [])
    {
        if ($this->fails) {
            $this->fails->set($fail, $context);
        } else {
            $this->fails = collect([$fail => $context], null, false);
        }
    }

    /**
     * 1. Check raw input validate rules and set prior the order for default and require rules
     * 2. Format every single rule item into [MSG, EXT]
     *
     * @param array $rules: Raw input validation rules
     */
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
                unset($rarr[0]);
                $ext = join(':', $rarr);

                $msg = $msg ?: $this->getDefaultRuleMessage($rule, $key, $ext);

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

    public function getDefaultRuleMessage(string $rule, string $key, string $ext) : string
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
        if ($rule === 'type') {
            $_rule .= ucfirst($ext);
        }

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
     * @param Dof\Framework\Collection $fails
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
     * @return Dof\Framework\Collection|null
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
