<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

final class TypeHint
{
    public static function convert($val, string $type)
    {
        $converter = 'convertTo'.ucfirst(strtolower($type));
        if (! method_exists(__CLASS__, $converter)) {
            exception('TypeHintConverterNotExists', ['type' => $type]);
        }

        return self::$converter($val);
    }

    public static function convertToString($val)
    {
        if (self::isString($val)) {
            return (string) $val;
        }

        exception('TypeHintConvertFailed', [
            '__error' => 'Unable to convert to string',
            'value'   => string_literal($val)
        ]);
    }

    public static function convertToInt($val)
    {
        if (self::isInt($val)) {
            return intval($val);
        }

        exception('TypeHintConvertFailed', [
            '__error' => 'Unable to convert to int',
            'value'   => string_literal($val),
        ]);
    }

    public static function isString($val) : bool
    {
        return is_scalar($val);
    }

    public static function isInt($val) : bool
    {
        if (! is_numeric($val)) {
            return false;
        }

        $_val = intval($val);

        return $val == $_val;
    }
}
