<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Closure;
use Throwable;
use Dof\Framework\Facade;
use Dof\Framework\Web\Response as Instance;
use Dof\Framework\Web\Route;

class Response extends Facade
{
    protected static $singleton = true;
    protected static $namespace = Instance::class;

    /**
     * Send a format-fixed exception response
     *
     * It's a system level error
     */
    public static function exception(int $status, string $message, array $context = [])
    {
        $context['__request'] = Request::getContext();
        Log::log('exception', $message, $context);
        unset($context['__request']);

        self::setInstance(Response::new())
            ->setStatus($status)
            ->setMimeAlias('json')
            ->send([$status, $message, $context]);
    }

    /**
     * Send a result response with dynamic format
     *
     * @param mixed{array|scalar|null} $result: the response data origin
     * @param bool|null $error: application logic level error
     */
    public static function send($result = null, ?bool $error = null, ?int $code = 200)
    {
        $response = self::getInstance();
        if (is_null($error)) {
            $error = $response->getError();
            $error = is_null($error) ? $response->isFailed() : $error;
        }

        if ($error) {
            $response->setError(true);
        }
        $wrapper = $error ? 'err' : 'out';
        $result  = self::package($result, $wrapper, Route::get("wrap{$wrapper}"), false);

        try {
            $mimeout = Route::get('suffix.current') ?: Route::get('mimeout');
            $response->setMimeAlias($mimeout)->setStatus($code)->send($result);
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
     * @param mixed $result: result data to response
     * @param array|string|null $wrapper: the wrapper used to package result data
     * @param bool $final: whether the $wrapper is the final wapper format data or just wapper location config
     * @return $result: Packaged response result
     */
    public static function package($result, string $type, $wrapper = null, bool $final = false)
    {
        if (is_null($result) && (! $wrapper)) {
            $result = '';
        }

        $wrapper = $final ? $wrapper : wrapper($wrapper, $type);
        if ((! $wrapper) || (! is_array($wrapper))) {
            return $result;
        }

        $data = [];
        if (ci_equal($type, 'err')) {
            foreach ($wrapper as $key => $default) {
                $_key = is_int($key) ? $default : $key;
                $_val = null;
                if (is_object($result)) {
                    $_val = ($result->{$default} ?? null) ?: ($result->{$key} ?? null);
                    if (is_null($_val) && method_exists($result, 'toArray')) {
                        $_res = $result->toArray();
                        if (is_array($_res)) {
                            $_val = ($_res[$default] ?? null) ?: ($_res[$key] ?? null);
                        }
                    } elseif (is_null($_val) && method_exists($result, '__toArray')) {
                        $_res = $result->__toArray();
                        if (is_array($_res)) {
                            $_val = ($_res[$default] ?? null) ?: ($_res[$key] ?? null);
                        }
                    }
                } elseif (is_array($result)) {
                    $_val = ($result[$default] ?? null) ?: ($result[$key] ?? null);
                }

                if (is_null($_val)) {
                    $_val = self::getInstance()->getWraperr($_key);
                }

                $data[$_key] = $_val;
            }
            return $data;
        }

        foreach ($wrapper as $key => $default) {
            if ($key === '__DATA__') {
                $data[$default] = $result;
                continue;
            }
            if ($key === '__PAGINATOR__') {
                $data[$default] = self::getInstance()->getWrapout('paginator');
                continue;
            }

            $_key = is_int($key) ? $default : $key;
            $_val = self::getInstance()->getWrapout($_key);
            $_val = is_null($_val) ? (is_int($key) ? null : $default) : $val;
            $data[$_key] = $_val;
        }
        return $data;
    }
}
