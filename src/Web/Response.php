<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Closure;
use Exception;
use Error;
use Loy\Framework\Base\Facade;
use Loy\Framework\Base\ApplicationService;
use Loy\Framework\Web\Http\Response as Instance;
use Loy\Framework\Web\Route;
use Loy\Framework\Web\WrapperManager;

class Response extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;

    public static function parseResult($result)
    {
        if ($result instanceof ApplicationService) {
            if ($result->isSuccess()) {
                return $result->__getData();
            }

            self::getInstance()->setStatus($result->__getCode());
            return array_values($result->__toArray());
        }

        return $result;
    }

    public static function send($result, ?bool $error = null)
    {
        $result   = self::parseResult($result);
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
                    $getter = 'get'.ucfirst(strtolower($_key));
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

            if (is_object($val)) {
                if (method_exists($val, '__toArray')) {
                    $val = $val->__toArray();
                } elseif (method_exists($val, 'toArray')) {
                    $val = $val->toArray();
                }
            }

            $data[$_key] = $val;
        }

        return $data;
    }
}
