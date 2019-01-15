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
