<?php

declare(strict_types=1);

namespace DOF;

final class Domain
{
    /**
     * Get meta data of domain of caller
     *
     * @param string|null $key
     * @param mixed $default
     *
     * @return mixed
     */
    public static function meta(string $key = null, $default = null)
    {
        if ($file = (\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null)) {
            return DMN::meta($file, $key, $default);
        }

        return $default;
    }

    /**
     * Get environment variables of domain of caller
     *
     * @param string|null $key
     * @param mixed $default
     *
     * @return mixed
     */
    public static function env(string $key = null, $default = null)
    {
        if ($file = (\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null)) {
            return ENV::domainGet($file, $key, $default);
        }

        return $default;
    }

    /**
     * Get final env config item for the domain of caller
     *
     * @return mixed
     */
    public static function envFinal(string $key, $default = null)
    {
        if ($file = (\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null)) {
            return ENV::final($file, $key, $default);
        }

        return $default;
    }

    /**
     * Get custom configs of domain of caller
     *
     * @param string|null $type
     * @param string|null $key
     * @param mixed $default
     *
     * @return mixed
     */
    public static function cfg(string $item = null, string $key = null, $default = null)
    {
        if ($file = (\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null)) {
            return ETC::domainGet($file, $item, $key, $default);
        }

        return $default;
    }

    /**
     * Get final custom configs for the domain of caller
     */
    public static function cfgFinal(string $item, string $key = null, $default = null)
    {
        if ($file = (\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null)) {
            return ETC::final($file, $item, $key, $default);
        }

        return $default;
    }

    /**
     * Get custom settings of domain of caller
     *
     * @param string|null $type
     * @param string|null $key
     * @param mixed $default
     *
     * @return mixed
     */
    public static function ini(string $item = null, string $key = null, $default = null)
    {
        if ($file = (\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null)) {
            return INI::domainGet($file, $item, $key, $default);
        }

        return $default;
    }

    /**
     * Get final setting for the domain of caller
     */
    public static function iniFinal(string $item, string $key, $default = null)
    {
        if ($file = (\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null)) {
            return INI::final($file, $item, $key, $default);
        }

        return $default;
    }

    public static function name()
    {
        if ($file = (\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null)) {
            return DMN::name($file);
        }

        return $default;
    }
}
