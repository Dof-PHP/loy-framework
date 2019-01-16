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
use Loy\Framework\Web\RouteManager;
use Loy\Framework\Web\Request;
use Loy\Framework\Web\Response;
use Loy\Framework\Web\Route;
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
        PipeManager::compile(DomainManager::getDomains());
    }

    public static function compileRoutes()
    {
        RouteManager::compile(DomainManager::getDomains());
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
        $paramsMethod = Route::get('method.params');
        $paramsRoute  = Route::get('params');
        if ((! $paramsMethod) && (! $paramsRoute)) {
            return [];
        }

        $class  = Route::get('class');
        $method = Route::get('method.name');
        $params = [];
        $vflag  = '$';
        foreach ($paramsMethod as $idx => $paramMethod) {
            $name = $paramMethod['name'] ?? '';
            $type = $paramMethod['type']['type'] ?? false;
            $builtin  = $paramMethod['type']['builtin'] ?? false;
            $optional = $paramMethod['optional'] ?? false;
            $error    = "{$class}@{$method}(... {$type} {$vflag}{$name} ...)";

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
            if ($optional) {
                break;
            }
            if ($builtin) {
                throw new PortMethodParameterMissingException($error);
            }
            try {
                $params[] = new $type;
            } catch (Exception | Error $e) {
                throw new BrokenHttpPortMethodDefinitionException("{$error} => {$e->getMessage()}");
            }
        }

        return $params;
    }
}
