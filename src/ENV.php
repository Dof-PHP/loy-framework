<?php

declare(strict_types=1);

namespace DOF;

use DOF\ETC;
use DOF\Convention;

final class ENV
{
    const KEY = 'env';

    public static function all()
    {
        return [
            Convention::SRC_SYSTEM => ENV::systemGet(),
            Convention::SRC_DOMAIN => ENV::domainGet(),
            Convention::SRC_VENDOR => ENV::vendorGet(),
        ];
    }

    public static function finalMatch(string $domain, array $keys, $default = null, string &$_key = null)
    {
        return ETC::finalMatch($domain, ENV::KEY, $keys, $default, $_key);
    }

    /**
     * Get domain final env item: domain -> system
     */
    public static function final(string $domain = null, string $key = null, $default = null)
    {
        $value = ENV::domainGet($domain, $key);
        if (! \is_null($value)) {
            return $value;
        }

        return ENV::systemGet($key, $default);
    }

    public static function systemMatch(array $keys, $default = null, string &$_key = null)
    {
        return ETC::systemMatch(ENV::KEY, $keys, $default, $_key);
    }

    public static function systemGet(string $key = null, $default = null)
    {
        return ETC::systemGet(ENV::KEY, $key, $default);
    }
   
    public static function systemSet(array $data)
    {
        ETC::systemSet(ENV::KEY, $data);
    }

    public static function domainMatch(string $domain, array $keys, $default = null, string &$_key = null)
    {
        return ETC::domainMatch($domain, ENV::KEY, $keys, $default, $_key);
    }

    public static function domainGet(string $domain = null, string $key = null, $default = null)
    {
        return ETC::domainGet($domain, ENV::KEY, $key, $default);
    }
 
    public static function domainSet(string $domain, array $data)
    {
        ETC::domainSet($domain, ENV::KEY, $data);
    }

    public static function vendorMatch(string $vendor, array $keys, $default = null, string &$_key = null)
    {
        return ETC::vendorMatch($vendor, ENV::KEY, $keys, $default, $_key);
    }

    public static function vendorGet(string $vendor = null, string $key = null, $default = null)
    {
        return ETC::vendorGet($vendor, ENV::KEY, $key, $default);
    }

    public static function vendorSet(string $vendor, array $data)
    {
        ETC::vendorSet($vendor, ENV::KEY, $data);
    }
}
