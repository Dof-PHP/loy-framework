<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Web\RouteManager;

class Route
{
    private static $data = [];
    private static $instance = null;

    public static function getData() : array
    {
        return self::$data;
    }

    public static function setData(array $data)
    {
        $data['all'] = RouteManager::getRoutes();
        self::$data     = $data;
        self::$instance = null;
    }

    public static function getInstance()
    {
        if (! self::$instance) {
            self::$instance = collect(self::$data, __CLASS__);
        }

        return self::$instance;
    }

    public static function get(string $key)
    {
        $getter = self::getInstance();
        if (! is_null($val = $getter->get($key))) {
            return $val;
        }

        $value  = null;
        $keyarr = explode('.', $key);
        foreach ($keyarr as $key) {
            $val = $getter->get($key);
            if (is_null($val)) {
                return null;
            }
            $value = $val;
            if (is_object($val) && method_exists($val, 'get')) {
                $getter = $val;
                continue;
            }
        }

        return $value;
    }
}
