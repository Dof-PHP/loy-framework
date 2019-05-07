<?php

declare(strict_types=1);

namespace Dof\Framework\Facade;

use Throwable;
use Dof\Framework\Facade;
use Dof\Framework\ConfigManager;
use Dof\Framework\Web\Response as Instance;
use Dof\Framework\Web\Port;
use Dof\Framework\Web\Route;

class Response extends Facade
{
    protected static $singleton = true;
    protected static $namespace = Instance::class;

    public static function abort(int $status, array $error, array $context = [], string $domain = null)
    {
        return self::error($status, $error, $context, $domain);
    }

    /**
     * It's a user level error
     *
     * @param int $status: HTTP response status
     * @param array $error: Error code with message
     * @param array $context: Error context
     * @param string $domain: Domain class namespace
     */
    public static function error(int $status, array $error, array $context = [], string $domain = null)
    {
        $code = (int) ($error[0] ?? -1);
        $info = (string) ($error[1] ?? -1);

        $debug = $domain
            ? ConfigManager::getDomainFinalEnvByNamespace($domain, 'HTTP_DEBUG', false)
            : ConfigManager::getEnv('HTTP_DEBUG', false);

        $body = $debug ? [$code, $info, $context] : [$code, $info];

        // We dont record user error coz it might be huge amount abused reqeusts

        $wraperr = Port::get('wraperr');
        $wraperr = $wraperr ? $wraperr : ConfigManager::getFramework('web.error.wrapper', null);
        if ($wraperr) {
            if (is_array($wraperr)) {
                $body = self::wraperr($body, $wraperr);
            } elseif (class_exists($wraperr)) {
                try {
                    $body = self::wraperr($body, wrapper($wraperr, 'err'));
                } catch (Throwable $e) {
                    // ignore if any exception thrown to avoid empty response
                }
            }
        }

        Response::getInstance()
            ->setStatus($status)
            ->setMimeAlias(self::mimeout())
            ->setError(true)
            ->setBody($body)
            ->send();
    }

    /**
     * It's a system level error
     *
     * @param int $status: HTTP response status
     * @param array $error: Error code with message
     * @param array $context: Exception context
     * @param string $domain: Domain class namespace
     */
    public static function exception(int $status, array $error, array $context = [], string $domain = null)
    {
        $context['__request'] = Request::getContext();
        Log::log('exception', join('-', $error), $context);
        unset($context['__request']);

        $code = (int) ($error[0] ?? -1);
        $info = (string) ($error[1] ?? -1);

        $debug = $domain
            ? ConfigManager::getDomainFinalEnvByNamespace($domain, 'HTTP_DEBUG', false)
            : ConfigManager::getEnv('HTTP_DEBUG', false);

        $body = $debug ? [$code, $info, $context] : [$code, $info];

        $wraperr = Port::get('wraperr');
        $wraperr = $wraperr ? $wraperr : ConfigManager::getFramework('web.exception.wrapper', null);
        if ($wraperr) {
            if (is_array($wraperr)) {
                $body = self::wraperr($body, $wraperr);
            } elseif (class_exists($wraperr)) {
                try {
                    $body = self::wraperr($body, wrapper($wraperr, 'err'));
                } catch (Throwable $e) {
                    // ignore if any exception thrown to avoid empty response
                }
            }
        }

        Response::getInstance()
        ->setError(true)
        ->setStatus($status)
        ->setMimeAlias('json')
        ->setBody($body)
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

    public static function wrapout($result, array $wrapper = null)
    {
        if (! $wrapper) {
            return $result;
        }

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

    public static function wraperr($result, array $wrapper = null)
    {
        if (! $wrapper) {
            return $result;
        }

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
