<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\AUX;

class Str
{
    public static function startWith(string $haystack, string $needle) : bool
    {
        $length = mb_strlen($needle);

        if (0 === $length) {
            return false;
        }

        return (mb_substr($haystack, 0, $length) === $needle);
    }

    public static function endWith(string $haystack, string $needle) : bool
    {
        $length = mb_strlen($needle);
        if (0 === $length) {
            return false;
        }

        return (mb_substr($haystack, -$length) === $needle);
    }

    public static function first(string $string, int $length = 1) : string
    {
        return mb_substr($string, 0, $length);
    }

    public static function last(string $string, int $length = 1) : string
    {
        return mb_substr($string, $length);
    }

    public static function mess(string $string) : string
    {
        $array = $_array = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
        $count = count($array);
        $result = '';

        for ($i = 0; $i < $count; $i++) {
            $_array = array_keys($_array);
            $max = count($_array) - 1;
            $idx = mt_rand(0, $max);
            $chr = $array[$_array[$idx]] ?? '';
            $result .= $chr;

            unset($_array[$idx]);
        }

        return $result;
    }

    public static function contain(string $haystack, string $needle) : bool
    {
        return (mb_strpos($haystack, $needle) !== false);
    }
}
