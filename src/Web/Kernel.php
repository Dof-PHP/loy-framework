<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Throwable;
use Loy\Framework\Kernel as Core;
use Loy\Framework\Container;
use Loy\Framework\RouteManager;
use Loy\Framework\TypeHint;
use Loy\Framework\Validator;
use Loy\Framework\Facade\Log;
use Loy\Framework\Facade\Request;
use Loy\Framework\Facade\Response;

/**
 * Loy Framework Web Kernel
 */
final class Kernel
{
    const PIPEIN_HANDLER  = 'pipein';
    const PIPEOUT_HANDLER = 'pipeout';
    const WRAPIN_HANDLER  = 'wrapin';
    const WRAPOUT_HANDLER = 'wrapout';
    const WRAPERR_HANDLER = 'wraperr';

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

        self::routing();

        $class  = Route::get('class');
        $method = Route::get('method.name');
        if (! class_exists($class)) {
            Kernel::throw('PortClassNotExist', compact('class'));
        }
        if (! method_exists($class, $method)) {
            Kernel::throw('PortMethodNotExist', compact('class', 'method'));
        }

        self::validate();

        self::pipingin();

        $params = self::build();

        try {
            $result = (new $class)->{$method}(...$params);
            // $result = call_user_func_array([(new $class), $method], $params);
        } catch (Throwable $e) {
            Kernel::throw('ResultingResponseFailed', compact('class', 'method'), 500, $e);
        }

        $result = self::pipingout($result);

        try {
            Response::send($result);
        } catch (Throwable $e) {
            Kernel::throw('SendResponseFailed', [], 500, $e);
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
        $route = RouteManager::find($uri, $verb, $mimes);
        if ($route === false) {
            Kernel::throw('RouteNotExists', [
                'method' => $verb,
                'uri'    => $uri
            ], 404);
        }
        Route::setData($route);
        Request::setRoute(Route::getInstance());

        if (($mimein = Route::get('mimein')) && (! Request::isMimeAlias($mimein))) {
            Kernel::throw('InvalidRequestMime', [
                'current' => Request::getMimeShort(),
                'require' => Request::getMimeByAlias($mimein),
            ], 400);
        }

        $class  = Route::get('class');
        $method = Route::get('method.name');
        if ($wrapin = Route::get('wrapin')) {
            $_wrapin = get_annotation_ns($wrapin, $class);
            if ((! $_wrapin) || (! class_exists($_wrapin))) {
                Kernel::throw('WrapperInNotExists', compact('wrapin', 'class', 'method'));
            }
            Route::set('wrapin', $_wrapin);
        }
        if ($wrapout = Route::get('wrapout')) {
            $_wrapout = get_annotation_ns($wrapout, $class);
            if ((! $_wrapout) || (! class_exists($_wrapout))) {
                Kernel::throw('WrapperOutNotExists', compact('wrapout', 'class', 'method'));
            }
            Route::set('wrapout', $_wrapout);
        }
        if ($wraperr = Route::get('wraperr')) {
            $_wraperr = get_annotation_ns($wraperr, $class);
            if ((! $_wraperr) || (! class_exists($_wraperr))) {
                Kernel::throw('WrapperErrNotExists', compact('wraperr', 'class', 'method'));
            }
            Route::set('wraperr', $_wraperr);
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
        $pipes = Route::get('pipes.out');
        $noout = Route::get('pipes.noout');
        if (count($pipes) === 0) {
            return $result;
        }

        $shouldPipeOutBeIgnored = function ($pipeout, $noout) : bool {
            foreach ($noout as $_exclude) {
                $exclude = get_annotation_ns($_exclude, Route::get('class'));
                if ((! $exclude) || (! class_exists($exclude))) {
                    Kernel::throw('NopipeoutClassNotExists', [
                        'nopipeout' => $_exclude,
                        'class'     => Route::get('class'),
                        'method'    => Route::get('method.name'),
                    ]);
                }
                if ($pipeout == $exclude) {
                    return true;
                }
            }

            return false;
        };

        $_result = $result;
        foreach ($pipes as $_pipe) {
            $pipe = get_annotation_ns($_pipe, Route::get('class'));
            if ($noout && $shouldPipeOutBeIgnored($pipe, $noout)) {
                continue;
            }
            if ((! $pipe) || (! class_exists($pipe))) {
                Kernel::throw('PipeOutClassNotExists', [
                    'port'    => Route::get('class'),
                    'method'  => Route::get('method.name'),
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
                    [$_result, Route::getInstance(), Request::getInstance(), Response::getInstance()]
                );
            } catch (Throwable $e) {
                Kernel::throw('PipeOutThroughFailed', compact('pipe'), 500, $e);
            }
        }

        return $_result;
    }

    /**
     * Request through port pipe-ins defined in current route
     */
    private static function pipingin()
    {
        $pipes = Route::get('pipes.in');
        $noin  = Route::get('pipes.noin');

        if (count($pipes) === 0) {
            return;
        }

        $shouldPipeInBeIgnored = function ($pipein, $noin) : bool {
            foreach ($noin as $_exclude) {
                $exclude = get_annotation_ns($_exclude, Route::get('class'));
                if ((! $exclude) || (! class_exists($exclude))) {
                    Kernel::throw('NopipeinClassNotExists', [
                        'nopipein' => $_exclude,
                        'class'    => Route::get('class'),
                        'method'   => Route::get('method.name'),
                    ]);
                }
                if ($pipein == $exclude) {
                    return true;
                }
            }

            return false;
        };

        foreach ($pipes as $_pipe) {
            $pipe = get_annotation_ns($_pipe, Route::get('class'));
            if ($noin && $shouldPipeInBeIgnored($pipe, $noin)) {
                continue;
            }
            if ((! $pipe) || (! class_exists($pipe))) {
                Kernel::throw('PipeInClassNotExists', [
                    'port'   => Route::get('class'),
                    'method' => Route::get('method.name'),
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
     * Validate request body parameters against route wrapperin definitions
     */
    private static function validate()
    {
        $wrapin = Route::get('wrapin');
        if (! $wrapin) {
            return;
        }
        if (! class_exists($wrapin)) {
            Kernel::throw('WrapperInNotExists', compact('wrapin'));
        }

        if (! method_exists($wrapin, self::WRAPIN_HANDLER)) {
            Kernel::throw('WrapperInHandlerNotExists', [
                'wrapin'  => $wrapin,
                'handler' => self::WRAPIN_HANDLER,
            ]);
        }

        try {
            $rules = call_user_func_array([singleton($wrapin), self::WRAPIN_HANDLER], []);
            if (! is_array($rules)) {
                Kernel::throw('InvalidWrapperinReturn', compact('wrapin', 'method', 'return'));
            }
            $params = array_keys($rules);
            $result = [];
            Validator::execute(Request::only($params), $rules, $result);

            Route::getInstance()->params->api = $result;
        } catch (Throwable $e) {
            Kernel::throw('ReqeustParameterValidationFailed', [], 500, $e);
        }
    }

    /**
     * Build port parameters from port method definition and route params
     */
    private static function build() : array
    {
        $paramsMethod = Route::get('method.params');
        $paramsRoute  = Route::get('params');
        if ((! $paramsMethod) && (! $paramsRoute)) {
            return [];
        }

        $class  = Route::get('class');
        $method = Route::get('method.name');
        $params = [];
        $count  = count($paramsMethod);
        foreach ($paramsMethod as $idx => $paramMethod) {
            $name = $paramMethod['name'] ?? null;
            $type = $paramMethod['type']['type'] ?? null;

            $builtin    = $paramMethod['type']['builtin'] ?? false;
            $optional   = $paramMethod['optional'] ?? false;
            $hasDefault = $paramMethod['default']['status'] ?? false;

            $port = compact('class', 'method', 'name', 'type');

            $paramExistsInRouteByName = array_key_exists($name, ($paramsRoute['raw'] ?? []));
            $paramExistsInRouteByIdx  = isset($paramsRoute['res'][$idx]);
            if ($paramExistsInRouteByName || $paramExistsInRouteByIdx) {
                $val = $paramExistsInRouteByName
                ? ($paramsRoute['kv'][$name] ?? null)
                : ($paramsRoute['res'][$idx] ?? null);

                if (is_null($val) && (! $optional)) {
                    Kernel::throw('MissingPortMethodParameter', $port);
                }
                try {
                    $val = TypeHint::convert($val, $type);
                } catch (Throwable $e) {
                    $name = 'TypeHintFailed';
                    $code = 500;
                    if (is_exception($e, 'TypeHintConvertFailed')) {
                        $name = 'InvalidRouteParameter';
                        $code = 400;
                    }
                    Kernel::throw($name, compact('val', 'type'), $code, $e);
                }
                $params[] = $val;
                continue;
            }

            // Ignore optional parameters check
            if ($optional && (($idx + 1) !== $count)) {
                break;
            }

            try {
                $params[] = Container::di($type);
            } catch (Throwable $e) {
                $error = ($builtin || (! $optional) || (! $hasDefault))
                ? 'MissingHttpPortParameters'
                : 'BrokenHttpPortDefinition';

                Kernel::throw($error, $port, 500, $e);
            }
        }

        return $params;
    }

    /**
     * Throw throwables from core kernel or web self in the web way
     *
     * @param string $name: Exception name
     * @param array $context: Exception Context
     * @param int $status: Error Code (compatible with HTTP status code)
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

    public static function getContext() : array
    {
        return [
            'web' => [
                Request::getContext(),
                Response::getContext(),
            ],
        ];
    }
}
