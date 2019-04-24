<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Dof\Framework\Facade;
use Dof\Framework\Web\Response as Instance;
use Dof\Framework\Web\Port;
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

        Response::new()
        ->setStatus($status)
        ->setMimeAlias('json')
        ->setBody([$status, $message, $context])
        ->send();
    }

    /**
     * Get the final mimeout for current http response
     */
    public static function mimeout()
    {
        $suffix = Route::get('suffix');
        if ($suffix && self::getInstance()->getMimeByAlias($suffix)) {
            return $suffix;
        }

        return Port::get('mimeout');
    }

    public static function wrapout($result, array $wrapper)
    {
        $data = [];

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

    public static function wraperr($result, array $wrapper)
    {
        $data = [];

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
}
