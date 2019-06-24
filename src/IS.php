<?php

declare(strict_types=1);

namespace Dof\Framework;

class IS
{
    public static function ciins(array $value, array $list) : bool
    {
        return ciins($value, $list);
    }

    public static function ciin($value, array $list) : bool
    {
        return ciin($value, $list);
    }

    public static function namespace($value) : bool
    {
        return is_string($value) && (
            false
            || class_exists($value)
            || interface_exists($value)
            || trait_exists($value)
        );
    }

    public static function mobile($value, string $type = 'cn') : bool
    {
        if ((! $value) || (! is_scalar($value))) {
            return false;
        }

        $value = (string) $value;

        switch ($type) {
            case 'cn':
            default:
                return 1 === preg_match('#^(\+86[\-\ ])?1\d{10}$#', $value);
        }

        return true;    // FIXME
    }

    public static function ipv6($value) : bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    public static function ipv4($value) : bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    public static function ip($value) : bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_IP);
    }

    public static function host($value) : bool
    {
        return (false
            || (false !== filter_var($value, FILTER_VALIDATE_DOMAIN))
            || (false !== filter_var($value, FILTER_VALIDATE_IP))
        );
    }

    public static function url($value) : bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_URL);
    }

    public static function email($value) : bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
