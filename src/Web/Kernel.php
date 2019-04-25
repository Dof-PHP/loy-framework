<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

use Throwable;
use Dof\Framework\Kernel as Core;
use Dof\Framework\Container;
use Dof\Framework\PortManager;
use Dof\Framework\WrapinManager;
use Dof\Framework\ConfigManager;
use Dof\Framework\TypeHint;
use Dof\Framework\Validator;
use Dof\Framework\Facade\Log;
use Dof\Framework\Facade\Request;
use Dof\Framework\Facade\Response;

/**
 * Dof Framework Web Kernel
 */
final class Kernel
{
    const PIPEIN_HANDLER  = 'pipein';
    const PIPEOUT_HANDLER = 'pipeout';
    const WRAPOUT_HANDLER = 'wrapout';
    const WRAPERR_HANDLER = 'wraperr';
    const PREFLIGHT_HANDLER = 'preflight';
    const HALT_FLAG = '.LOCK.WEB.DOF';

    private static $booted = false;

    /**
     * Web kernel handler - The entry of HTTP world
     *
     * Bootstrap framework kernel and then process HTTP request
     *
     * @param string $root
     */
    public static function handle(string $root)
    {
        // Deny all non-cgi calls
        if (! in_array(PHP_SAPI, ['fpm-fcgi', 'cgi-fcgi', 'cgi', 'cli-server'])) {
            exit('RunWebKernelInNonCGI');
        }

        self::$booted = true;

        try {
            Core::register('shutdown', function () {
                $uptime   = $_SERVER['REQUEST_TIME_FLOAT'] ?? Core::getUptime();
                $duration = microtime(true) - $uptime;
                $memcost  = memory_get_usage() - Core::getUpmemory();
                Log::log('http', enjson([
                    $duration,
                    $memcost,
                    memory_get_peak_usage(),
                    count(get_included_files()),
                ]), Kernel::getContext());
            });

            Core::boot($root);
        } catch (Throwable $e) {
            Kernel::throw('KernelBootFailed', ['root' => $root], 500, $e);
        }

        if (is_file($flag = ospath($root, Kernel::HALT_FLAG))) {
            Kernel::throw('ServerClosed', dejson($flag, true, true), 503);
        }

        try {
            self::preflight();
        } catch (Throwable $e) {
            Kernel::throw('PreflightingError', [], 500, $e);
        }

        try {
            self::routing();
        } catch (Throwable $e) {
            Kernel::throw('RoutingError', [], 500, $e);
        }

        $class  = Port::get('class');
        $method = Port::get('method');
        if ((! $class) || (! class_exists($class))) {
            Kernel::throw('PortClassNotExist', compact('class'));
        }
        if ((! $method) || (! method_exists($class, $method))) {
            Kernel::throw('PortMethodNotExist', compact('class', 'method'));
        }

        try {
            self::validate();
        } catch (Throwable $e) {
            Kernel::throw('RequestValidateExeception', [], 500, $e);
        }

        try {
            self::pipingin();
        } catch (Throwable $e) {
            Kernel::throw('PipinginError', [], 500, $e);
        }

        try {
            $params = self::build();
        } catch (Throwable $e) {
            Kernel::throw('BuildPortParametersError', [], 500, $e);
        }

        try {
            $result = (new $class)->{$method}(...$params);
        } catch (Throwable $e) {
            Kernel::throw('ResultingResponseFailed', compact('class', 'method'), 500, $e);
        }

        try {
            $result = self::pipingout($result);
        } catch (Throwable $e) {
            Kernel::throw('PipingoutError', [], 500, $e);
        }

        try {
            $result = self::packing($result);
        } catch (Throwable $e) {
            Kernel::throw('PackagingError', [], 500, $e);
        }

        try {
            Response::setBody($result)->send();
        } catch (Throwable $e) {
            Kernel::throw('SendResponseFailed', [], 500, $e);
        }
    }

    /**
     * Preflight stuffs before process request
     */
    private static function preflight()
    {
        $preflights = ConfigManager::getDomain('http.preflight', []);
        if ((! $preflights) || (! is_array($preflights))) {
            return;
        }

        foreach ($preflights as $preflight) {
            if (! class_exists($preflight)) {
                Kernel::throw('PreflightNotExists', compact('preflight'));
            }
            if (! method_exists($preflight, self::PREFLIGHT_HANDLER)) {
                Kernel::throw('PreflightHandlerNotExists');
            }

            if (true !== ($res = call_user_func_array([singleton($preflight), self::PREFLIGHT_HANDLER], [
                Request::getInstance(),
                Response::getInstance()
            ]))) {
                Kernel::throw('PreflightingFailed', compact('res'));
            }
        }
    }

    /**
     * Routing logics
     *
     * 1. Find route definition by request information
     * 2. Validate request uri against route definition
     */
    private static function routing()
    {
        $verb  = Request::getVerb();
        $uri   = Request::getUri();
        $mimes = Request::getMimeAliases();
        $route = PortManager::find($uri, $verb, $mimes);
        if (! $route) {
            $mime = Request::getMimeShort();
            Kernel::throw('RouteNotExists', compact('verb', 'uri', 'mime'), 404);
        }
        $port = PortManager::get($route);
        if (! $port) {
            Kernel::throw('BadRouteWithoutPort', compact('route'), 500);
        }

        Route::setData($route);
        Port::setData($port);
        Response::setMimeAlias(Response::mimeout());

        if (($mimein = Port::get('mimein')) && (! Request::isMimeAlias($mimein))) {
            Kernel::throw('InvalidRequestMime', [
                'current' => Request::getMimeShort(),
                'require' => Request::getMimeByAlias($mimein, '?'),
            ], 400);
        }
    }

    /**
     * Validate request body parameters against route definitions
     * - either: wrapin check
     * - or: argument annotations check
     */
    private static function validate()
    {
        $wrapin = Port::get('wrapin');
        $arguments = Port::get('__arguments');
        if ((! $wrapin) && (! $arguments)) {
            return;
        }

        // 1. Check wrapin setting on route annotation first
        // 2. Check arguments annotations from route method and port properties
        try {
            $validator = $wrapin ? WrapinManager::apply($wrapin) : WrapinManager::execute($arguments, Route::get('class'));
            if (($fails = $validator->getFails()) && ($fail = $fails->first())) {
                $context = (array) $fail->value;
                if ($wrapin) {
                    $context['wrapins'][] = $wrapin;
                }

                Response::error(400, $fail->key, $context);
            }

            Port::getInstance()->argument = collect($validator->getResult());
        } catch (Throwable $e) {
            Kernel::throw('ReqeustParameterValidationError', compact('wrapin'), 500, $e);
        }
    }

    /**
     * Request through port pipe-ins defined in current route
     */
    private static function pipingin()
    {
        $pipes = Port::get('pipein');
        $noin  = Port::get('nopipein');
        if (count($pipes) === 0) {
            return;
        }

        $shouldPipeInBeIgnored = function ($pipein, $noin) : bool {
            foreach ($noin as $exclude) {
                if ((! $exclude) || (! class_exists($exclude))) {
                    Kernel::throw('NopipeinClassNotExists', [
                        'nopipein' => $_exclude,
                        'class'    => Route::get('class'),
                        'method'   => Route::get('method'),
                    ]);
                }
                if ($pipein == $exclude) {
                    return true;
                }
            }

            return false;
        };

        foreach ($pipes as $pipe) {
            if ($noin && $shouldPipeInBeIgnored($pipe, $noin)) {
                continue;
            }
            if ((! $pipe) || (! class_exists($pipe))) {
                Kernel::throw('PipeInClassNotExists', [
                    'port'   => Route::get('class'),
                    'method' => Route::get('method'),
                    'pipein' => $_pipe,
                ]);
            }
            if (! method_exists($pipe, Kernel::PIPEIN_HANDLER)) {
                Kernel::throw('PipeInHandlerNotExists', [
                    'pipein'  => $pipe,
                    'hanlder' => Kernel::PIPEIN_HANDLER
                ]);
            }

            try {
                $res = call_user_func_array([singleton($pipe), Kernel::PIPEIN_HANDLER], [
                    Request::getInstance(),
                    Response::getInstance(),
                    Route::getInstance(),
                    Port::getInstance()
                ]);
                if (true !== $res) {
                    Kernel::throw('PipeInThroughFailed', [
                        'pipe'  => $pipe,
                        'error' => string_literal($res),
                    ], 400);
                }
            } catch (Throwable $e) {
                $error = is_anonymous($e) ? 400 : 500;
                Kernel::throw('PipeInThroughingFailed', compact('pipe'), $error, $e);
            }
        }
    }

    /**
     * Build port parameters from port method definition and route params
     */
    private static function build() : array
    {
        $paramsMethod = Port::get('__parameters');
        $paramsRoute  = Route::get('params.kv');
        if (($paramsMethod->count() < 1) && ($paramsRoute->count() < 1)) {
            return [];
        }

        try {
            return Container::complete($paramsMethod, $paramsRoute);
        } catch (Throwable $e) {
            $class  = Route::get('class');
            $method = Route::get('method');
            $name = 'BuildPortParametersFailed';
            $code = 500;
            if (is_exception($e, 'TypeHintConvertFailed')) {
                $name = 'InvalidRouteParameter';
                $code = 400;
            }
            Kernel::throw($name, compact('class', 'method'), $code, $e);
        }
    }

    /**
     * Response result through port pipe-outs defined in current route
     *
     * @param mixed $result
     * @return mixed Pipeouted response result
     */
    private static function pipingout($result)
    {
        $pipes = Port::get('pipeout');
        $noout = Port::get('nopipeout');
        if (count($pipes) === 0) {
            return $result;
        }

        $shouldPipeOutBeIgnored = function ($pipeout, $noout) : bool {
            foreach ($noout as $exclude) {
                if ((! $exclude) || (! class_exists($exclude))) {
                    Kernel::throw('NopipeoutClassNotExists', [
                        'nopipeout' => $_exclude,
                        'class'     => Route::get('class'),
                        'method'    => Route::get('method'),
                    ]);
                }
                if ($pipeout == $exclude) {
                    return true;
                }
            }

            return false;
        };

        $_result = $result;
        foreach ($pipes as $pipe) {
            if ($noout && $shouldPipeOutBeIgnored($pipe, $noout)) {
                continue;
            }
            if ((! $pipe) || (! class_exists($pipe))) {
                Kernel::throw('PipeOutClassNotExists', [
                    'port'    => Route::get('class'),
                    'method'  => Route::get('method'),
                    'pipeout' => $_pipe,
                ]);
            }
            if (! method_exists($pipe, Kernel::PIPEOUT_HANDLER)) {
                Kernel::throw('PipeOutHandlerNotExists', [
                    'pipeout' => $pipe,
                    'hanlder' => Kernel::PIPEOUT_HANDLER
                ]);
            }

            try {
                $_result = call_user_func_array(
                    [singleton($pipe), Kernel::PIPEOUT_HANDLER],
                    [$_result, Route::getInstance(), Port::getInstance(), Request::getInstance(), Response::getInstance()]
                );
            } catch (Throwable $e) {
                Kernel::throw('PipeOutThroughFailed', compact('pipe'), 500, $e);
            }
        }

        return $_result;
    }

    /**
     * Package response result with given wrapper (if exists)
     *
     * @param mixed $result: result data to response
     * @return $result: Packaged response result
     */
    private static function packing($result = null)
    {
        $isError = Response::hasError();
        $wrapout = Port::get('wrapout');
        $wraperr = Port::get('wraperr');
        $wrapper = $isError ? wrapper($wraperr, 'err') : wrapper($wrapout, 'out');

        if ((! $wrapper) || (! is_array($wrapper))) {
            return $result;
        }

        return $isError ? Response::wraperr($result, $wrapper) : Response::wrapout($result, $wrapper);
    }

    /**
     * Throw throwables from core kernel or web self in the web way
     *
     * @param string $name: Exception name
     * @param array $context: Exception Context
     * @param int $status: Error Code (compatible with HTTP status code)
     * @param Throwable $previous: Previous exception in chain
     * @return null
     */
    public static function throw(
        string $name,
        array $context = [],
        int $status = 500,
        Throwable $previous = null
    ) {
        Response::exception($status, $name, parse_throwable($previous, $context));
    }

    public static function isBooted() : bool
    {
        return self::$booted;
    }

    public static function getContext(bool $sapi = true) : array
    {
        $context = [
            Request::getContext(),
            Response::getContext(),
        ];

        return $sapi ? ['web' => $context] : $context;
    }
}
