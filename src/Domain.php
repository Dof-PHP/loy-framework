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
        $file = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null;
        if (! $file) {
            return $default;
        }

        return DMN::meta($file, $key, $default);
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
        $file = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null;
        if (! $file) {
            return $default;
        }

        return ENV::domainGet($file, $key, $default);
    }

    /**
     * Get final env config item for the domain of caller
     *
     * @return mixed
     */
    public static function envFinal(string $key, $default = null)
    {
        $file = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null;
        if (! $file) {
            return $default;
        }

        return ENV::final($file, $key, $default);
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
        $file = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null;
        if (! $file) {
            return $default;
        }

        return ETC::domainGet($file, $item, $key, $default);
    }

    /**
     * Get final custom configs for the domain of caller
     */
    public static function cfgFinal(string $item, string $key = null, $default = null)
    {
        $file = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null;
        if (! $file) {
            return $default;
        }

        return ETC::final($file, $item, $key, $default);
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
        $file = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null;
        if (! $file) {
            return $default;
        }

        return INI::domainGet($file, $item, $key, $default);
    }

    /**
     * Get final setting for the domain of caller
     */
    public static function iniFinal(string $item, string $key, $default = null)
    {
        $file = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null;
        if (! $file) {
            return $default;
        }

        return INI::final($file, $item, $key, $default);
    }

    public static function name()
    {
        $file = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file'] ?? null;
        if (! $file) {
            return $default;
        }

        return DMN::name($file);
    }
}
