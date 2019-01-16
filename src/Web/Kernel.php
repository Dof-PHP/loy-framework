<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Exception;
use Error;
use Loy\Framework\Base\Kernel as CoreKernel;
use Loy\Framework\Base\DomainManager;
use Loy\Framework\Base\TypeHint;
use Loy\Framework\Base\Exception\InvalidProjectRootException;
use Loy\Framework\Base\Exception\TypeHintConverterNotExistsException;
use Loy\Framework\Base\Exception\TypeHintConvertException;
use Loy\Framework\Base\Exception\InvalidAnnotationDirException;
use Loy\Framework\Base\Exception\InvalidAnnotationNamespaceException;
use Loy\Framework\Base\Exception\DuplicateRouteDefinitionException;
use Loy\Framework\Base\Exception\DuplicateRouteAliasDefinitionException;
use Loy\Framework\Base\Exception\DuplicatePipeDefinitionException;
use Loy\Framework\Web\RouteManager;
use Loy\Framework\Web\Request;
use Loy\Framework\Web\Response;
use Loy\Framework\Web\Route;
use Loy\Framework\Web\Exception\InvalidRouteDirException;
use Loy\Framework\Web\Exception\InvalidHttpPortNamespaceException;
use Loy\Framework\Web\Exception\DuplicateRouteDefinitionException as DuplicateRouteDefinitionExceptionWeb;
use Loy\Framework\Web\Exception\DuplicateRouteAliasDefinitionException as DuplicateRouteAliasDefinitionWeb;
use Loy\Framework\Web\Exception\PipeNotExistsException;
use Loy\Framework\Web\Exception\FrameworkCoreException;
use Loy\Framework\Web\Exception\PipeThroughFailedException;
use Loy\Framework\Web\Exception\RouteNotExistsException;
use Loy\Framework\Web\Exception\InvalidRequestMimeException;
use Loy\Framework\Web\Exception\InvalidUrlParameterException;
use Loy\Framework\Web\Exception\BadHttpPortCallException;
use Loy\Framework\Web\Exception\PortNotExistException;
use Loy\Framework\Web\Exception\PortMethodNotExistException;
use Loy\Framework\Web\Exception\PortMethodParameterMissingException;
use Loy\Framework\Web\Exception\BrokenHttpPortMethodDefinitionException;
use Loy\Framework\Web\Exception\ResponseWrapperNotExists;
use Loy\Framework\Web\Exception\InvalidHttpPipeDirException;
use Loy\Framework\Web\Exception\InvalidHttpPipeNamespaceException;
use Loy\Framework\Web\Exception\DuplicatePipeDefinitionException as DuplicatePipeDefinitionExceptionWeb;

final class Kernel extends CoreKernel
{
    const PIPE_HANDLER = 'through';

    public static function handle(string $projectRoot)
    {
        try {
            parent::handle($projectRoot);
        } catch (InvalidProjectRootException $e) {
            throw new FrameworkCoreException("InvalidProjectRootException => {$e->getMessage()}");
        }

        self::compileRoutes();
        self::compilePipes();
        self::processRequest();
    }

    public static function compilePipes()
    {
        try {
            PipeManager::compile(DomainManager::getDomains());
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
            RouteManager::compile(DomainManager::getDomains());
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

        $class  = Route::get('class');
        $method = Route::get('method.name');
        if (! class_exists($class)) {
            throw new PortNotExistException($class);
        }
        if (! method_exists($class, $method)) {
            throw new PortMethodNotExistException("{$class}@{$method}");
        }

        try {
            $params = self::buildPortMethodParameters();
            $result = call_user_func_array([(new $class), $method], $params);

            Response::send($result, false);
        } catch (Exception | Error $e) {
            throw new BadHttpPortCallException("{$class}@{$method}: {$e->getMessage()}");
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
                throw new PipeThroughFailedException($pipe." ({$e->getMessage()})");
            }
        }
    }

    private static function findAndSetRoute()
    {
        $method = Request::getMethod();
        $uri    = Request::getUri();
        $route  = RouteManager::findRouteByUriAndMethod($uri, $method);
        if ($route === false) {
            throw new RouteNotExistsException("{$method} {$uri}");
        }
        Route::setData($route);
    }

    private static function validateRoute()
    {
        $mimein = Route::get('mimein');
        if ($mimein && (! Request::isMimeAlias($mimein))) {
            $_mimein  = Request::getMimeShort();
            $__mimein = Request::getMimeByAlias($mimein);
            throw new InvalidRequestMimeException("{$_mimein} (NEED => {$__mimein})");
        }

        $wrapout = Route::get('wrapout');
        if ($wrapout && (! Response::hasWrapper($wrapout))) {
            throw new ResponseWrapperNotExists("{$wrapout} (WRAPOUT)");
        }
        $wraperr = Route::get('wraperr');
        if ($wraperr && (! Response::hasWrapper($wraperr))) {
            throw new ResponseWrapperNotExists("{$wraperr} (WRAPERR)");
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
                $params[] = new $type;
            } catch (Exception | Error $e) {
                throw ($builtin || (! $optional) || (! $hasDefault))
                ? new PortMethodParameterMissingException($error)
                : new BrokenHttpPortMethodDefinitionException("{$e->getMessage()} ({$error})");
            }
        }

        return $params;
    }
}
