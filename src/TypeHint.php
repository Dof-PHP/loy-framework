<?php

declare(strict_types=1);

namespace Dof\Framework;

final class TypeHint
{
    const SUPPORTS = [
        'int'    => true,
        'string' => true,
    ];

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

        if (is_null($val)) {
            return '';
        }

        exception('TypeHintConvertFailed', [
            'error' => 'Unable to convert to string',
            'value' => string_literal($val)
        ]);
    }

    public static function convertToInt($val)
    {
        if (self::isInt($val)) {
            return intval($val);
        }

        exception('TypeHintConvertFailed', [
            'error' => 'Unable to convert to int',
            'value' => string_literal($val),
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

    public static function support(string $type = null) : bool
    {
        if (! $type) {
            return false;
        }

        return self::SUPPORTS[$type] ?? false;
    }
}
