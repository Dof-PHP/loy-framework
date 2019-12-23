<?php

declare(strict_types=1);

namespace DOF;

use DOF\INI;
use DOF\Convention;
use DOF\Traits\Config;
use DOF\Util\IS;
use DOF\Util\Format;

final class I18N
{
    use Config;
    
    const ROOT = Convention::DIR_LANG;

    public static function lang(string $domain = null) : string
    {
        $default = INI::systemGet('domain', 'LANG_DEFAULT', 'en');

        return $domain ? ENV::final($domain, 'LANG_DEFAULT', $default) : ENV::systemGet('LANG_DEFAULT', $default);
    }

    public static function active(string $lang = null, string $domain = null) : bool
    {
        $enabled = INI::systemGet('domain', 'ENABLE_I18N', false);
        $enabled = $domain ? ENV::final($domain, 'ENABLE_I18N', $enabled) : $enabled;
        if (! $enabled) {
            return false;
        }

        if ($lang) {
            return $enabled && (! Str::eq(self::lang($domain), $lang, true));
        }

        return true;
    }

    public static function get(string $domain, string $key, string $lang = 'en', array $context = [])
    {
        $item = self::final($domain, $lang, $key);
        if (\is_null($item)) {
            return $key;
        }

        return Format::interpolate($item, $context);
    }

    public static function domain(string $domain, string $key, string $lang = 'en', array $context = [])
    {
        $item = self::domainGet($domain, $lang, $key);
        if (\is_null($item)) {
            return $key;
        }

        return Format::interpolate($item, $context);
    }

    public static function vendor(string $vendor, string $key, string $lang = 'en', array $context = [])
    {
        $item = self::vendorGet($vendor, $lang, $key);
        if (\is_null($item)) {
            return $key;
        }

        return Format::interpolate($item, $context);
    }

    public static function system(string $key, string $lang = 'en', array $context = [])
    {
        $item = self::systemGet($lang, $key);
        if (\is_null($item)) {
            return $key;
        }

        return Format::interpolate($item, $context);
    }
}
