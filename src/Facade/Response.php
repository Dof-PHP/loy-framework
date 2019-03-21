<?php

declare(strict_types=1);

namespace Loy\Framework\Facade;

use Closure;
use Throwable;
use Loy\Framework\Facade;
use Loy\Framework\WrapperManager;
use Loy\Framework\DDD\ApplicationService;
use Loy\Framework\Web\Response as Instance;
use Loy\Framework\Web\Route;

class Response extends Facade
{
    protected static $singleton = true;
    protected static $namespace = Instance::class;

    /**
     * Recognize supported result types and set particular attributes to properties of current response
     *
     * @param mixed $result: Respond result
     */
    public static function support($result)
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

    /**
     * Send a format-fixed exception response
     *
     * It's a system level error
     */
    public static function exception(int $status, string $message, array $context = [])
    {
        Log::log('exception', $message, $context);

        $response = Response::new();
        self::setInstance($response);

        $response
            ->setStatus($status)
            // ->setError(true)
            ->setInfo($message)
            ->send([$status, $message]);
    }

    /**
     * Send a result response with dynamic format
     *
     * @param mixed $result: the response data origin
     * @param bool|null $error: application logic level error
     */
    public static function send($result, ?bool $error = null)
    {
        $result   = self::support($result);
        $response = self::getInstance();
        if (is_null($error)) {
            $error = $response->getError();
            $error = is_null($error) ? $response->isFailed() : $error;
        }

        $wrapout = $error ? Route::get('wraperr') : Route::get('wrapout');
        $wrapper = WrapperManager::getWrapper(($error ? 'err' : 'out'), $wrapout);
        $result  = self::package($result, $wrapper);

        try {
            $mimeout = Route::get('suffix.current') ?: Route::get('mimeout');
            $response->setMimeAlias($mimeout)->send($result);
        } catch (Throwable $e) {
            Response::exception(
                500,
                'SendingResponseError',
                parse_throwable($e, compact('mimeout', 'wrapper', 'wrapout'))
            );
        }
    }

    /**
     * Package response result with given wrapper (if exists)
     *
     * @param $result mixed: result data to response
     * @param $wrapper array: the wrapper used to package result data
     * @param $final bool: whether the $wrapper is the final wapper format data or just wapper location config
     * @return $result: Packaged response result
     */
    public static function package($result, array $wrapper = null, bool $final = false)
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
