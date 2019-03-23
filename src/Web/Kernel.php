<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Throwable;
use Loy\Framework\Kernel as Core;
use Loy\Framework\Container;
use Loy\Framework\RouteManager;
use Loy\Framework\PipeManager;
use Loy\Framework\WrapperManager;
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
    /**
     * Web kernel handler - The entry of HTTP world
     *
     * Bootstrap framework kernel and then process HTTP request
     *
     * @param string $root
     * @return null
     */
    public static function handle(string $root)
    {
        // Deny all non-cgi calls
        if (! in_array(PHP_SAPI, ['fpm-fcgi', 'cgi-fcgi', 'cgi', 'cli-server'])) {
            exit('RunWebKernelInNonCGI');
        }

        try {
            Core::register('shutdown', function () {
                $uptime   = $_SERVER['REQUEST_TIME_FLOAT'] ?? Core::getUptime();
                $duration = microtime(true) - $uptime;
                Log::log('http', $duration, [
                    'in'  => Request::getContext(),
                    'out' => Response::getContext(),
                ]);
            });

            Core::boot($root);
        } catch (Throwable $e) {
            Kernel::throw('KernelBootFailed', ['root' => $root], 500, $e);
        }

        self::routing();

        $class  = Route::get('class');
        $method = Route::get('method.name');
        if (! class_exists($class)) {
            Kernel::throw('PortClassNotExist', ['class' => $class]);
        }
        if (! method_exists($class, $method)) {
            Kernel::throw('PortMethodNotExist', compact('class', 'method'));
        }

        self::validate();

        self::piping();

        $params = self::build();

        try {
            $result = call_user_func_array([(new $class), $method], $params);
        } catch (Throwable $e) {
            Kernel::throw('ResultingResponseFailed', compact('class', 'method'), 500, $e);
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
        if (($wrapin = Route::get('wrapin')) && (! WrapperManager::hasWrapperIn($wrapin))) {
            Kernel::throw('WrapperInNotExists', compact('wrapin', 'class', 'method'));
        }
        if (($wrapout = Route::get('wrapout')) && (! WrapperManager::hasWrapperOut($wrapout))) {
            Kernel::throw('WrapperOutNotExists', compact('wrapout', 'class', 'method'));
        }
        if (($wraperr = Route::get('wraperr')) && (! WrapperManager::hasWrapperErr($wraperr))) {
            Kernel::throw('WrapperErrNotExists', compact('wraperr', 'class', 'method'));
        }
    }

    /**
     * Through port pipes defined in current route
     */
    private static function piping()
    {
        $pipes   = PipeManager::getPipes();
        $aliases = Route::get('pipes');
        if (! $aliases) {
            return;
        }
        foreach ($aliases as $alias) {
            $pipe = $pipes[$alias] ?? false;
            if (! $pipe) {
                Kernel::throw('PipeAliasNotExists', compact('alias'));
            }
            if (! class_exists($pipe)) {
                Kernel::throw('PipeClassNotExists', compact('pipe'));
            }
            if (! method_exists($pipe, PipeManager::PIPE_HANDLER)) {
                Kernel::throw('PipeHandlerNotExists', [
                    'class'   => $pipe,
                    'hanlder' => PipeManager::PIPE_HANDLER
                ]);
            }

            try {
                $res = call_user_func_array([(new $pipe), PipeManager::PIPE_HANDLER], [
                    Request::getInstance(),
                    Response::getInstance(),
                    Route::getInstance(),
                ]);
                if (true !== $res) {
                    Kernel::throw('PipeThroughFailed', [
                        'pipe'  => $pipe,
                        'error' => string_literal($res),
                    ], 400);
                }
            } catch (Throwable $e) {
                Kernel::throw('PipeThroughFailed', compact('pipe'), 500, $e);
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
        $wrapper = WrapperManager::getWrapperIn($wrapin);
        if (! $wrapper) {
            Kernel::throw('WrapperInNotExists', compact('wrapper'));
        }

        $class  = $wrapper['class']  ?? '?';
        $method = $wrapper['method'] ?? '?';
        if (! class_exists($class)) {
            Kernel::throw('WrapperInClassNotExists', compact('class'));
        }
        if (! method_exists($class, $method)) {
            Kernel::throw('WrapperInMethodNotExists', [
                'class'  => $class,
                'method' => $method,
            ]);
        }

        try {
            $rules = call_user_func_array([(new $class), $method], []);
            if (! is_array($rules)) {
                Kernel::throw('InvalidWrapperinReturn', compact('class', 'method', 'return'));
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
            $port = compact('class', 'method', 'name', 'type');
            $builtin    = $paramMethod['type']['builtin'] ?? false;
            $optional   = $paramMethod['optional'] ?? false;
            $hasDefault = $paramMethod['default']['status'] ?? false;

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
                    Kernel::throw('TypeHintFailed', [], 500, $e);
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
}
