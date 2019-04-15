<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

use Throwable;
use Dof\Framework\Kernel as Core;
use Dof\Framework\Container;
use Dof\Framework\RouteManager;
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
            Kernel::throw('ServerClosed', dejson(file_get_contents($flag)), 503);
        }

        try {
            self::routing();
        } catch (Throwable $e) {
            Kernel::throw('RoutingError', [], 500, $e);
        }

        $class  = Route::get('class');
        $method = Route::get('method.name');
        if (! class_exists($class)) {
            Kernel::throw('PortClassNotExist', compact('class'));
        }
        if (! method_exists($class, $method)) {
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
            // $result = call_user_func_array([(new $class), $method], $params);
        } catch (Throwable $e) {
            Kernel::throw('ResultingResponseFailed', compact('class', 'method'), 500, $e);
        }

        try {
            $result = self::pipingout($result);
        } catch (Throwable $e) {
            Kernel::throw('PipingoutError', [], 500, $e);
        }

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
            Kernel::throw('RouteNotExists', compact('verb', 'uri'), 404);
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
     * Validate request body parameters against route definitions
     * - either: wrapin check
     * - or: argument annotations check
     */
    private static function validate()
    {
        $wrapin = Route::get('wrapin');
        $arguments = Route::get('arguments');
        if ((! $wrapin) && (! $arguments)) {
            return;
        }

        // 1. Check wrapin setting on route annotation first
        // 2. Check arguments annotations from route method and port properties
        try {
            $validator = $wrapin ? Wrapin::apply($wrapin) : Wrapin::execute($arguments, Route::get('class'));
            if (($fails = $validator->getFails()) && ($fail = $fails->first())) {
                $context = (array) $fail->value;
                if ($wrapin) {
                    $context['wrapins'][] = $wrapin;
                }
                Response::send([400, $fail->key, $context], true, 400);
            }
            Route::getInstance()->params->api = collect($validator->getResult());
        } catch (Throwable $e) {
            Kernel::throw('ReqeustParameterValidationError', compact('wrapin'), 500, $e);
        }
    }

    /**
     * Build port parameters from port method definition and route params
     */
    private static function build() : array
    {
        $paramsMethod = Route::get('method.params');
        $paramsRoute  = Route::get('params.kv');
        if (($paramsMethod->count() < 1) && ($paramsRoute->count() < 1)) {
            return [];
        }

        try {
            return Container::complete($paramsMethod, $paramsRoute);
        } catch (Throwable $e) {
            $class  = Route::get('class');
            $method = Route::get('method.name');
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

    public static function getContext(bool $sapi = true) : array
    {
        $context = [
            Request::getContext(),
            Response::getContext(),
        ];

        return $sapi ? ['web' => $context] : $context;
    }
}
