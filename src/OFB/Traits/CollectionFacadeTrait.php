<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Traits;

trait CollectionFacadeTrait
{
    private static $data = [];
    private static $instance;

    public static function getData() : array
    {
        return self::$data;
    }

    public static function setData(array $data)
    {
        self::$data = $data;
        self::$instance = null;
    }

    public static function getInstance()
    {
        if (! self::$instance) {
            self::$instance = collect(self::$data, __CLASS__);
        }

        return self::$instance;
    }

    public static function set(string $key, $value)
    {
        self::getInstance()->set($key, $value);
    }

    public static function getRaw(string $key, $default = null)
    {
        $val = self::get($key, $default);
        if (is_null($val)) {
            return null;
        }

        if (is_collection($val)) {
            return uncollect($val);
        }

        return $val;
    }

    public static function get(string $key, $default = null)
    {
        $key = strtolower($key);
        $obj = self::getInstance();
        $val = $obj->get($key);
        if (! is_null($val)) {
            return $val;
        }

        $value  = null;
        $keyarr = explode('.', $key);
        foreach ($keyarr as $key) {
            $val = $obj->get($key);
            if (is_null($val)) {
                break;
            }
            $value = $val;
            if (is_object($val) && method_exists($val, 'get')) {
                $obj = $val;
                continue;
            }
        }

        return is_null($value) ? $default : $value;
    }
}
