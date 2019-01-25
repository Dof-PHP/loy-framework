<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Exception;
use Error;
use Loy\Framework\Base\Kernel as CoreKernel;
use Loy\Framework\Base\Container;
use Loy\Framework\Base\TypeHint;
use Loy\Framework\Base\Validator;
use Loy\Framework\Base\Exception\InvalidProjectRootException;
use Loy\Framework\Base\Exception\MethodNotExistsException;
use Loy\Framework\Base\Exception\TypeHintConverterNotExistsException;
use Loy\Framework\Base\Exception\TypeHintConvertException;
use Loy\Framework\Base\Exception\ValidationFailureException;
use Loy\Framework\Base\Exception\InvalidAnnotationDirException;
use Loy\Framework\Base\Exception\InvalidAnnotationNamespaceException;
use Loy\Framework\Base\Exception\DuplicateRouteDefinitionException;
use Loy\Framework\Base\Exception\DuplicateRouteAliasDefinitionException;
use Loy\Framework\Base\Exception\DuplicatePipeDefinitionException;
use Loy\Framework\Base\Exception\DuplicateWrapperDefinitionException;
use Loy\Framework\Web\RouteManager;
use Loy\Framework\Web\PipeManager;
use Loy\Framework\Web\WrapperManager;
use Loy\Framework\Web\Request;
use Loy\Framework\Web\Response;
use Loy\Framework\Web\Route;
use Loy\Framework\Web\Exception\InvalidRouteDirException;
use Loy\Framework\Web\Exception\InvalidHttpPortNamespaceException;
use Loy\Framework\Web\Exception\BadRouteWrapperInExecutionException;
use Loy\Framework\Web\Exception\WrapperInNotExistsException;
use Loy\Framework\Web\Exception\DuplicateRouteDefinitionException as DuplicateRouteDefinitionExceptionWeb;
use Loy\Framework\Web\Exception\DuplicateRouteAliasDefinitionException as DuplicateRouteAliasDefinitionExceptionWeb;
use Loy\Framework\Web\Exception\PipeNotExistsException;
use Loy\Framework\Web\Exception\FrameworkCoreException;
use Loy\Framework\Web\Exception\PipeThroughFailedException;
use Loy\Framework\Web\Exception\RouteNotExistsException;
use Loy\Framework\Web\Exception\InvalidRequestMimeException;
use Loy\Framework\Web\Exception\InvalidUrlParameterException;
use Loy\Framework\Web\Exception\MethodNotExistsException as MethodNotExistsExceptionWeb;
use Loy\Framework\Web\Exception\BadHttpPortCallException;
use Loy\Framework\Web\Exception\BadRequestParameterException;
use Loy\Framework\Web\Exception\PortNotExistException;
use Loy\Framework\Web\Exception\PortMethodNotExistException;
use Loy\Framework\Web\Exception\PortMethodParameterMissingException;
use Loy\Framework\Web\Exception\BrokenHttpPortMethodDefinitionException;
use Loy\Framework\Web\Exception\ResponseWrapperNotExists;
use Loy\Framework\Web\Exception\InvalidHttpPipeDirException;
use Loy\Framework\Web\Exception\InvalidHttpWrapperNamespaceException;
use Loy\Framework\Web\Exception\InvalidHttpWrapperDirException;
use Loy\Framework\Web\Exception\InvalidHttpPipeNamespaceException;
use Loy\Framework\Web\Exception\DuplicatePipeDefinitionException as DuplicatePipeDefinitionExceptionWeb;
use Loy\Framework\Web\Exception\DuplicateWrapperDefinitionException as DuplicateWrapperDefinitionExceptionWeb;
use Loy\Framework\Web\Exception\InvalidWrapperinReturnValueException;

final class Kernel extends CoreKernel
{
    const PIPE_HANDLER = 'through';

    public static function handle(string $root)
    {
        if (! in_array(PHP_SAPI, ['fpm-fcgi', 'cgi-fcgi', 'cgi'])) {
            echo 'WEB_KERNEL_IN_NONCGI';
            exit(-1);
        }

        try {
            parent::handle($root);
        } catch (InvalidProjectRootException $e) {
            throw new FrameworkCoreException("InvalidProjectRootException => {$root}", 500);
        } catch (Exception | Error $e) {
            throw new FrameworkCoreException(__CLASS__, 500, $e);
        }

        self::compileRoutes();
        self::compilePipes();
        self::compileWrappers();
        self::processRequest();
    }

    public static function compileWrappers()
    {
        try {
            CoreKernel::compileWrapper();
        } catch (DuplicateWrapperDefinitionException $e) {
            throw new DuplicateWrapperDefinitionExceptionWeb($e->getMessage());
        } catch (InvalidAnnotationDirException $e) {
            throw new InvalidHttpWrapperDirException($e->getMessage());
        } catch (InvalidAnnotationNamespaceException $e) {
            throw new InvalidHttpWrapperNamespaceException($e->getMessage());
        }
    }

    public static function compilePipes()
    {
        try {
            CoreKernel::compilePipe();
        } catch (DuplicatePipeDefinitionException $e) {
            throw new DuplicatePipeDefinitionExceptionWeb($e->getMessage());
        } catch (InvalidAnnotationDirException $e) {
            throw new InvalidHttpPipeDirException($e->getMessage());
        } catch (InvalidAnnotationNamespaceException $e) {
            throw new InvalidHttpPipeNamespaceException($e->getMessage());
        }
    }

    public static function compileRoutes()
    {
        try {
            CoreKernel::compileRoute();
        } catch (DuplicateRouteDefinitionException $e) {
            throw new DuplicateRouteDefinitionExceptionWeb($e->getMessage());
        } catch (DuplicateRouteAliasDefinitionException $e) {
            throw new DuplicateRouteAliasDefinitionExceptionWeb($e->getMessage());
        } catch (InvalidAnnotationDirException $e) {
            throw new InvalidRouteDirException($e->getMessage());
        } catch (InvalidAnnotationNamespaceException $e) {
            throw new InvalidHttpPortNamespaceException($e->getMessage());
        }
    }

    private static function processRequest()
    {
        self::findAndSetRoute();
        self::processRoutePipes();
        self::validateRoute();
        self::processRouteWrapperin();

        $class  = Route::get('class');
        $method = Route::get('method.name');
        if (! class_exists($class)) {
            throw new PortNotExistException($class);
        }
        if (! method_exists($class, $method)) {
            throw new PortMethodNotExistException("{$class}@{$method}");
        }

        $params = self::buildPortMethodParameters();

        try {
            $result = call_user_func_array([(new $class), $method], $params);
        } catch (MethodNotExistsException $e) {
            throw new MethodNotExistsExceptionWeb($e->getMessage());
        } catch (Exception | Error $e) {
            throw new BadHttpPortCallException("PORT: {$class}@{$method}", 500, $e);
        }

        try {
            Response::send($result);
        } catch (Exception | Error $e) {
            throw new FrameworkCoreException("ResponseError => {$e->getMessage()}", 500, $e->getTraceAsString());
        }
    }

    private static function processRouteWrapperin()
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
            throw new WrapperInNotExistsException("{$class} (NAMESPACE)");
        }
        if (! method_exists($class, $method)) {
            throw new WrapperInNotExistsException("{$class}@{$method} (METHOD)");
        }

        try {
            $rules  = call_user_func_array([(new $class), $method], []);
            if (! is_array($rules)) {
                throw new InvalidWrapperinReturnValueException("{$class}@{$method} (Array Required)");
            }
            $params = array_keys($rules);
            $result = [];
            Validator::execute(Request::only($params), $rules, $result);

            Route::getInstance()->params->api = $result;
        } catch (ValidationFailureException $e) {
            throw new BadRequestParameterException($e->getMessage(), 400);
        } catch (Exception | Error $e) {
            throw new BadRouteWrapperInExecutionException($e->getMessage());
        }
    }

    private static function processRoutePipes()
    {
        $pipes   = PipeManager::getPipes();
        $aliases = Route::get('pipes');
        if (! $aliases) {
            return;
        }
        foreach ($aliases as $alias) {
            $pipe = $pipes[$alias] ?? false;
            if (! $pipe) {
                throw new PipeNotExistsException($alias.' (ALIAS)');
            }
            if (! class_exists($pipe)) {
                throw new PipeNotExistsException($pipe.' (NAMESPACE)');
            }
            if (! method_exists($pipe, self::PIPE_HANDLER)) {
                throw new PipeNotExistsException($pipe.' (HANDLER)');
            }

            try {
                if (true !== ($res = call_user_func_array([(new $pipe), self::PIPE_HANDLER], [
                    Request::getInstance(),
                    Response::getInstance(),
                ]))) {
                    $res = string_literal($res);
                    throw new PipeThroughFailedException($pipe." ({$res})");
                }
            } catch (Exception | Error $e) {
                throw new PipeThroughFailedException($pipe, 400, $e);
            }
        }
    }

    private static function findAndSetRoute()
    {
        $method = Request::getMethod();
        $uri    = Request::getUri();
        $mimes  = Request::getMimeAliases();
        $route  = RouteManager::findRouteByUriAndMethod($uri, $method, $mimes);
        if ($route === false) {
            throw new RouteNotExistsException("{$method} {$uri}");
        }

        Route::setData($route);
        Request::setRoute(Route::getInstance());
    }

    private static function validateRoute()
    {
        $class  = Route::get('class') ?: '?';
        $method = Route::get('method.name') ?: '?';
        $mimein = Route::get('mimein');
        if ($mimein && (! Request::isMimeAlias($mimein))) {
            $_mimein  = Request::getMimeShort();
            $__mimein = Request::getMimeByAlias($mimein);
            throw new InvalidRequestMimeException("{$_mimein} (NEED => {$__mimein})");
        }
        $wrapin = Route::get('wrapin');
        if ($wrapin && (! WrapperManager::hasWrapperIn($wrapin))) {
            throw new ResponseWrapperNotExists("{$wrapin} (WRAPIN: {$class}@{$method})");
        }
        $wrapout = Route::get('wrapout');
        if ($wrapout && (! WrapperManager::hasWrapperOut($wrapout))) {
            throw new ResponseWrapperNotExists("{$wrapout} (WRAPOUT: {$class}@{$method})");
        }
        $wraperr = Route::get('wraperr');
        if ($wraperr && (! WrapperManager::hasWrapperErr($wraperr))) {
            throw new ResponseWrapperNotExists("{$wraperr} (WRAPERR: {$class}@{$method})");
        }
    }

    private static function buildPortMethodParameters() : array
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
                    throw new PortMethodParameterMissingException($error);
                }
                try {
                    $val = TypeHint::convert($val, $type);
                } catch (TypeHintConverterNotExistsException $e) {
                    throw new FrameworkCoreException("TypeHintConverterNotExistsException => {$e->getMessage()}");
                } catch (TypeHintConvertException $e) {
                    throw new InvalidUrlParameterException($e->getMessage());
                }
                $params[] = $val;
                continue;
            }
            if ($optional && (($idx + 1) !== $count)) {
                break;
            }
            try {
                $params[] = Container::di($type);
            } catch (Exception | Error $e) {
                throw ($builtin || (! $optional) || (! $hasDefault))
                ? new PortMethodParameterMissingException($error)
                : new BrokenHttpPortMethodDefinitionException("{$error} ({$e->getMessage()})");
            }
        }

        return $params;
    }
}
