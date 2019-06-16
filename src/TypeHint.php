<?php

declare(strict_types=1);

namespace Dof\Framework;

final class TypeHint
{
    const SUPPORTS = [
        'uint' => true,
        'pint' => true,
        'bint' => true,
        'int' => true,
        'integer' => true,
        'double' => true,
        'float' => true,
        'string' => true,
        'array' => true,
        'bool' => true,
        'boolean' => true,

        // SQL column type compatible
        'bigint' => true,
        'tinyint' => true,
        'smallint' => true,
        'mediumint' => true,
        'varchar' => true,
        'char' => true,
    ];

    public static function convert($val, string $type, bool $force = false)
    {
        $converter = 'convertTo'.ucfirst(strtolower($type));
        if (! method_exists(__CLASS__, $converter)) {
            exception('TypeHintConverterNotExists', ['type' => $type]);
        }

        return self::$converter($val, $force);
    }

    public static function convertToArray($val, bool $force = false) : array
    {
        if ($val instanceof Collection) {
            return (array) $val->toArray();
        }

        return (array) $val;
    }

    public static function convertToChar($val, bool $force = false)
    {
        return self::convertToString($val, $force);
    }

    public static function convertToVarchar($val, bool $force = false)
    {
        return self::convertToString($val, $force);
    }

    public static function convertToString($val, bool $force = false)
    {
        if ($force) {
            return strval($force);
        }

        if (self::isString($val)) {
            return (string) $val;
        }

        if (is_null($val)) {
            return '';
        }

        exception('TypeHintStringFailed', compact('val'));
    }

    public static function convertToPint($val, bool $force = false)
    {
        $val = $force ? intval($val) : self::convertToInt($val);

        if ($val < 1) {
            exception('TypeHintPintFailed', compact('val'));
        }

        return $val;
    }

    public static function convertToBint($val, bool $force = false)
    {
        $val = $force ? intval($val) : self::convertToInt($val);

        if (($val !== 0) && ($val !== 1)) {
            exception('TypeHintBintFailed');
        }

        return $val;
    }

    public static function convertToUint($val, bool $force = false)
    {
        $val = $force ? intval($val) : self::convertToInt($val);

        if ($val < 0) {
            exception('TypeHintUintFailed', compact('val'));
        }

        return $val;
    }

    public static function convertToInteger($val, bool $force = false)
    {
        return self::convertToInt($val, $force);
    }

    public static function convertToBigint($val, bool $force = false)
    {
        return self::convertToInt($val, $force);
    }

    public static function convertToSmallint($val, bool $force = false)
    {
        return self::convertToInt($val, $force);
    }

    public static function convertToTinyint($val, bool $force = false)
    {
        return self::convertToInt($val, $force);
    }

    public static function convertToInt($val, bool $force = false)
    {
        if ($force) {
            return intval($val);
        }

        if (self::isInt($val)) {
            return intval($val);
        }

        exception('TypeHintIntFailed', compact('val'));
    }

    public static function convertToBoolean($val, bool $force = false) : bool
    {
        return self::convertToBool($val, $force);
    }

    public static function convertToBool($val, bool $force = false) : bool
    {
        return boolval($val);
    }

    public static function isArray($val) : bool
    {
        return is_array($val) || ($val instanceof Collection);
    }

    public static function isChar($val) : bool
    {
        return self::isString($val);
    }

    public static function isVarchar($val) : bool
    {
        return self::isString($val);
    }

    public static function isString($val) : bool
    {
        return is_scalar($val);
    }

    public static function isPint($val) : bool
    {
        if (! self::isUint($val)) {
            return false;
        }

        return $val > 0;
    }

    public static function isBigint($val, bool $unsigned = false) : bool
    {
        if (! self::isInt($val)) {
            return false;
        }

        return $unsigned
            ? (($val >= 0) && ($val <= (pow(2, 64) - 1)))
            : (($val >= -(pow(2, 63))) && ($val <= (pow(2, 63) - 1)));
    }

    public static function isMediumint($val, bool $unsigned = false) : bool
    {
        if (! self::isInt($val)) {
            return false;
        }

        return $unsigned
            ? (($val >= 0) && ($val <= 16777215))
            : (($val >= -8388608) && ($val <= 8388607));
    }

    public static function isSmallint($val, bool $unsigned = false) : bool
    {
        if (! self::isInt($val)) {
            return false;
        }

        return $unsigned
            ? (($val >= 0) && ($val <= 65535))
            : (($val >= -32768) && ($val <= 32767));
    }


    public static function isTinyint($val, bool $unsigned = false) : bool
    {
        if (! self::isInt($val)) {
            return false;
        }

        return $unsigned
            ? (($val >= 0) && ($val <= 255))
            : (($val >= -128) && ($val <= 127));
    }

    public static function isBint($val) : bool
    {
        if (! self::isInt($val)) {
            return false;
        }

        $val = self::convertToInt($val);

        return ($val === 0) || ($val === 1);
    }

    public static function isUint($val) : bool
    {
        if (! self::isInt($val)) {
            return false;
        }

        return $val >= 0;
    }

    public static function isBool($val) : bool
    {
        return is_bool($val);
    }

    public static function isBoolean($val) : bool
    {
        return self::isBool($val);
    }

    public static function isInteger($val) : bool
    {
        return self::isInt($val);
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

        $type = strtolower(trim($type));

        return self::SUPPORTS[$type] ?? false;
    }
}
