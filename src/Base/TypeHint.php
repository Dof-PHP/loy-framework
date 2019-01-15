<?php

declare(strict_types=1);

namespace Loy\Framework\Base;

use Loy\Framework\Base\Exception\TypeHintConverterNotExistsException;
use Loy\Framework\Base\Exception\TypeHintConvertException;

final class TypeHint
{
    public static function convert($val, string $type)
    {
        $converter = 'convertTo'.ucfirst(strtolower($type));
        if (! method_exists(__CLASS__, $converter)) {
            throw new TypeHintConverterNotExistsException($type);
        }

        return self::$converter($val);
    }

    public static function convertToInt($val)
    {
        if (self::isInt($val)) {
            return intval($val);
        }

        throw new TypeHintConvertException('Unable to convert to int => '.string_literal($val));
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
