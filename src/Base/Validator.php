<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\TypeHint;
use Loy\Framework\Base\Exception\ValidatorNotFoundException;
use Loy\Framework\Base\Exception\ValidationFailureException;
use Loy\Framework\Base\Exception\TypeHintConvertException;

class Validator
{
    protected $data = [];
    protected $rule = [];
    protected $message = [];
    protected $errors  = [];

    public function __construct(array $data = [], array $rule = [], array $message = [])
    {
        $this->data = $data;
        $this->rule = $rule;
        $this->message = $message;
    }

    public function hasError() : bool
    {
        return count($this->errors) > 0;
    }

    public function execute()
    {
        pp($this->data, $this->rule, $this->message);
        foreach ($this->rule as $key => $rules) {
            if (! is_string($key)) {
                // TODO: BAD RULE
                continue;
            }
            if (! array_key_exists($key, $this->data)) {
                continue;
            }
            $val = $this->data[$key];

            $rules = explode('|', $rules);
            foreach ($rules as $rule) {
                $arr   = explode(':', $rule);
                $rule  = trim($arr[0] ?? '');
                if (! $rule) {
                    continue;
                }
                $validator = 'validate'.ucfirst(strtolower($rule));
                if (! method_exists($this, $validator)) {
                    throw new ValidatorNotFoundException($rule);
                }

                $param = $arr[1] ?? [];
            }
        }

        // throw new ValidationFailureException('以下参数必须是正整数: id');
    }

    public function validateNeed()
    {
    }

    public function getErrors()
    {
        return collect($this->errors);
    }

    public static function __callStatic(string $method, array $params)
    {
        return call_user_func_array([(new static), $method], $params);
    }

    public static function check(array $data, array $rule = [], array $message = [])
    {
        return (new static($data, $rule, $message))->execute();
    }
}
