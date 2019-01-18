<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Closure;
use Exception;
use Error;
use Loy\Framework\Base\Facade;
use Loy\Framework\Web\Http\Response as Instance;
use Loy\Framework\Web\Route;
use Loy\Framework\Web\WrapperManager;

class Response extends Facade
{
    public static $singleton    = true;
    protected static $namespace = Instance::class;

    public static function send($result, ?bool $error = null)
    {
        $response = self::getInstance();
        if (is_null($error)) {
            $error = $response->getError();
            $error = is_null($error) ? $response->isFailed() : $error;
        }

        $wrapout = $error ? Route::get('wraperr') : Route::get('wrapout');
        $wrapper = $error ? WrapperManager::getWrapperErr($wrapout) : WrapperManager::getWrapperOut($wrapout);
        $result  = self::setWrapperOnResult($result, $wrapper);

        try {
            $mimeout = Route::get('suffix.current') ?: Route::get('mimeout');
            $response->setMimeAlias($mimeout)->send($result);
        } catch (Exception | Error $e) {
            Response::new()
            ->setStatus(500)
            ->send(self::setWrapperOnResult([
                objectname($e),
                $e->getCode(),
                $e->getMessage(),
            ], WrapperManager::getWrapperErr(Route::get('wraperr'))));
        }
    }

    public static function setWrapperOnResult($result, array $wrapper = null, bool $final = false)
    {
        $wrapper = $final ? $wrapper : WrapperManager::getWrapperFinal($wrapper);
        if ((! $wrapper) || (! is_array($wrapper))) {
            return $result;
        }

        $data = [];
        $idx  = -1;
        foreach ($wrapper as $key => $default) {
            $_key = is_int($key)    ? $default : $key;
            $_val = is_string($key) ? $default : null;
            ++$idx;
            $val = null;
            if (is_object($result)) {
                if (method_exists($result, '__toArray')) {
                    $val = $result->__toArray();
                } elseif (method_exists($result, 'toArray')) {
                    $val = $result->toArray();
                } else {
                    $getter = 'get'.ucfirst(strtolower($key));
                    if (method_exists($result, $getter)) {
                        $val = $result->{$getter}();
                    }
                }
            } elseif (is_scalar($result)) {
                if (0 === $idx) {
                    $val = $result;
                } else {
                    $val = $_val;
                }
            } elseif (is_array($result)) {
                $val = $result[$_key] ?? ($result[$idx] ?? $_val);
            }

            $data[$_key] = $val;
        }

        return $data;
    }
}
