<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Throwable;
use Loy\Framework\Base\Kernel as CoreKernel;
use Loy\Framework\Base\Container;
use Loy\Framework\Base\TypeHint;
use Loy\Framework\Base\Validator;

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
            CoreKernel::boot($root);
        } catch (Throwable $e) {
            Kernel::throw($e);
        }

        self::routing();
        $class  = Route::get('class');
        $method = Route::get('method.name');
        if (! class_exists($class)) {
            Kernel::throw('PortNotExistException', ['class' => $class]);
        }
        if (! method_exists($class, $method)) {
            Kernel::throw('PortMethodNotExistException', [
                'class'  => $class,
                'method' => $method,
            ]);
        }

        self::throughPipes();
        self::validateParameters();
        $params = self::buildParameters();

        try {
            Response::send(call_user_func_array([(new $class), $method], $params));
        } catch (Throwable $e) {
            Kernel::throw($e, ['class' => $class, 'method' => $method]);
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
            Kernel::throw('RouteNotExistsException', [
                'method' => $verb,
                'uri'    => $uri
            ]);
        }
        Route::setData($route);
        Request::setRoute(Route::getInstance());

        if (($mimein = Route::get('mimein')) && (! Request::isMimeAlias($mimein))) {
            Kernel::throw('InvalidRequestMimeException', [
                'current' => Request::getMimeShort(),
                'require' => Request::getMimeByAlias($mimein),
            ]);
        }

        $class  = Route::get('class');
        $method = Route::get('method.name');
        if (($wrapin = Route::get('wrapin')) && (! WrapperManager::hasWrapperIn($wrapin))) {
            Kernel::throw('ResponseWrapperNotExists', [
                'type'   => 'wrapin',
                'wapper' => $wrapin,
                'class'  => $class,
                'method' => $method,
            ]);
        }
        if (($wrapout = Route::get('wrapout')) && (! WrapperManager::hasWrapperOut($wrapout))) {
            Kernel::throw('ResponseWrapperNotExists', [
                'type'   => 'wrapout',
                'wapper' => $wrapout,
                'class'  => $class,
                'method' => $method,
            ]);
        }
        if (($wraperr = Route::get('wraperr')) && (! WrapperManager::hasWrapperErr($wraperr))) {
            Kernel::throw('ResponseWrapperNotExists', [
                'type'   => 'wraperr',
                'wapper' => $wraperr,
                'class'  => $class,
                'method' => $method,
            ]);
        }
    }

    /**
     * Through port pipes defined in current route
     */
    private static function throughPipes()
    {
        $pipes   = PipeManager::getPipes();
        $aliases = Route::get('pipes');
        if (! $aliases) {
            return;
        }
        foreach ($aliases as $alias) {
            $pipe = $pipes[$alias] ?? false;
            if (! $pipe) {
                Kernel::throw('PipeNotExistsException', ['type' => 'alias']);
            }
            if (! class_exists($pipe)) {
                Kernel::throw('PipeNotExistsException', ['type' => 'namespace']);
            }
            if (! method_exists($pipe, PipeManager::PIPE_HANDLER)) {
                Kernel::throw('PipeNotExistsException', ['type' => 'handler']);
            }

            try {
                $res = call_user_func_array([(new $pipe), PipeManager::PIPE_HANDLER], [
                    Request::getInstance(),
                    Response::getInstance(),
                ]);
                if (true !== $res) {
                    Kernel::throw('PipeThroughFailedException', [
                        'pipe' => $pipe,
                        'error' => string_literal($res),
                    ]);
                }
            } catch (Throwable $e) {
                Kernel::throw($e);
            }
        }
    }

    /**
     * Validate request body parameters against route wrapperin definitions
     */
    private static function validateParameters()
    {
        $wrapin = Route::get('wrapin');
        if (! $wrapin) {
            return;
        }
        $wrapper = WrapperManager::getWrapperIn($wrapin);
        if (! $wrapper) {
            return;
        }

        $class  = $wrapper['class']  ?? '?';
        $method = $wrapper['method'] ?? '?';
        if (! class_exists($class)) {
            Kernel::throw('WrapperInNotExistsException', [
                'type'  => 'namespace',
                'class' => $class,
            ]);
        }
        if (! method_exists($class, $method)) {
            Kernel::throw('WrapperInNotExistsException', [
                'type'   => 'method',
                'class'  => $class,
                'method' => $method,
            ]);
        }

        try {
            $rules = call_user_func_array([(new $class), $method], []);
            if (! is_array($rules)) {
                Kernel::throw('InvalidWrapperinReturnValueException', [
                    'class'  => $class,
                    'method' => $method,
                ]);
            }
            $params = array_keys($rules);
            $result = [];
            Validator::execute(Request::only($params), $rules, $result);

            Route::getInstance()->params->api = $result;
        } catch (Throwable $e) {
            Kernel::throw($e);
        }
    }

    /**
     * Build port parameters from port method definition and route params
     */
    private static function buildParameters() : array
    {
        $route = Route::getData();
        $paramsMethod = $route['method']['params'] ?? [];
        $paramsRoute  = $route['params'] ?? [];
        if ((! $paramsMethod) && (! $paramsRoute)) {
            return [];
        }

        $class  = Route::get('class');
        $method = Route::get('method.name');
        $params = [];
        $vflag  = '$';
        $count  = count($paramsMethod);
        foreach ($paramsMethod as $idx => $paramMethod) {
            $name  = $paramMethod['name'] ?? '';
            $type  = $paramMethod['type']['type'] ?? false;
            $error = "{$class}@{$method}(... {$type} {$vflag}{$name} ...)";
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
                    Kernel::throw('PortMethodParameterMissingException', ['error' => $error]);
                }
                try {
                    $val = TypeHint::convert($val, $type);
                } catch (Throwable $e) {
                    Kernel::throw($e);
                }
                $params[] = $val;
                continue;
            }
            if ($optional && (($idx + 1) !== $count)) {
                break;
            }
            try {
                $params[] = Container::di($type);
            } catch (Throwable $e) {
                $_e = ($builtin || (! $optional) || (! $hasDefault))
                ? 'PortMethodParameterMissingException'
                : 'BrokenHttpPortMethodDefinitionException';

                Kernel::throw($_e, ['error'  => $error, 'origin' => $e->getMessage()]);
            }
        }

        return $params;
    }

    /**
     * Throw throwables from core kernel or web self in the web way
     *
     * @param $throwable object (core kernel) | string (web self)
     * @return null
     */
    public static function throw($throwable, array $context = [])
    {
        if (is_object($throwable)) {
            $base = get_class($throwable);
            $web  = str_replace('Base', 'Web', $base);
        } elseif (is_string($throwable)) {
            $web = "Loy\\Framework\\Web\\Exception\\{$throwable}";
        }

        dd($web, class_exists($web), $context);
    }
}
