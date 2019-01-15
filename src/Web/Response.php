<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Closure;
use Loy\Framework\Web\Http\Response as Instance;
use Loy\Framework\Base\Facade;

class Response extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;

    protected static $wrappers = [];

    public static function setWrapperOnResult($result, $wrapper = null)
    {
        if (is_null($wrapper)) {
            return $result;
        }
        if (is_string($wrapper)) {
            if (! self::hasWrapper($wrapper)) {
                return $result;
            }

            $wrapper = self::getWrapper($wrapper);
        } elseif (is_array($wrapper)) {
        } else {
            return $result;
        }

        $data = [];
        $idx  = -1;
        foreach ($wrapper as $key) {
            ++$idx;
            if (is_object($result)) {
                $getter = 'get'.ucfirst(strtolower($key));
                $val = null;
                if (method_exists($result, $getter)) {
                    $val = $result->{$getter}();
                }
                $data[$key] = $val;
                continue;
            }

            $val = $result[$key] ?? ($result[$idx] ?? null);
            $data[$key] = $val;
        }

        return $data;
    }

    public static function hasWrapper(string $key) : bool
    {
        return isset(self::$wrappers[$key]) && is_array(self::$wrappers[$key]);
    }

    public static function getWrapper(string $key) : ?array
    {
        return self::$wrappers[$key] ?? null;
    }

    public static function getWrappers()
    {
        return self::$wrappers;
    }

    public static function addWrapper(string $key, array $wrapper)
    {
        self::$wrappers[$key] = $wrapper;
    }
}
