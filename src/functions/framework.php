<?php

declare(strict_types=1);

if (! function_exists('collect')) {
    function collect(array $data, $origin = null)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = collect($value, $origin);
            }
        }

        return new \Loy\Framework\Base\Collection($data, $origin);
    }
}
if (! function_exists('validate')) {
    function validate(array $data, array $rule = [], array $message = [])
    {
        return \Loy\Framework\Base\Validator::check($data, $rule, $message);
    }
}
