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

    public static function processRequest()
    {
        $method = Request::getMethod();
        $uri    = Request::getUri();
        $route  = RouteManager::findRouteByUriAndMethod($uri, $method);
        if ($route === false) {
            throw new RouteNotExistsException("{$method} {$uri}");
        }
        Route::setData($route);

        $mimein = $route['mimein'] ?? false;
        if ($mimein && (! Request::isMimeAlias($mimein))) {
            $_mimein  = Request::getMimeShort();
            $__mimein = Request::getMimeByAlias($mimein);
            throw new InvalidRequestMimeException("{$_mimein} (NEED => {$__mimein})");
        }

        $class  = $route['class']  ?? '-';
        $method = $route['method']['name'] ?? '-';
        if (! class_exists($class)) {
            throw new PortNotExistException($class);
        }
        $port = new $class;
        if (! method_exists($port, $method)) {
            throw new PortMethodNotExistException("{$class}@{$method}");
        }

        $pipes = PipeManager::getPipes();
        foreach (($route['pipes'] ?? []) as $alias) {
            $pipe = $pipes[$alias] ?? false;
            if (! $pipe) {
                throw new PipeNotExistsException($alias.' (ALIAS)');
            }
            if (! class_exists($pipe)) {
                throw new PipeNotExistsException($pipe.' (NAMESPACE)');
            }
            $_pipe = new $pipe;
            if (! method_exists($_pipe, self::PIPE_HANDLER)) {
                throw new PipeNotExistsException($pipe.' (HANDLER)');
            }

            try {
                if (true !== ($res = call_user_func_array([$_pipe, self::PIPE_HANDLER], [
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

        $wrapper = false;
        if ($wrapout = ($route['wrapout'] ?? false)) {
            if (! Reponse::hasWrapper($wrapout)) {
                throw new ResponseWrapperNotExists($wrapout);
            }
        }

        try {
            $params = self::buildPortMethodParameters($route);
            $result = call_user_func_array([$port, $method], $params);
            $result = Response::setWrapperOnResult($result, $wrapper);

            Response::setMimeAlias($route['mimeout'] ?? null)->send($result);
        } catch (Exception | Error $e) {
            throw new BadHttpPortCallException("{$class}@{$method}: {$e->getMessage()}");
        }
    }

    private static function buildPortMethodParameters(array $route) : array
    {
        $paramsMethod = $route['method']['params'] ?? [];
        $paramsRoute  = $route['params'] ?? [];
        if ((! $paramsMethod) && (! $paramsRoute)) {
            return [];
        }

        $class  = $route['class'] ?? '?';
        $method = $route['method']['name'] ?? '?';
        $params = [];
        $vflag  = '$';
        foreach ($paramsMethod as $paramMethod) {
            $name = $paramMethod['name'] ?? '';
            $type = $paramMethod['type']['type'] ?? false;
            $builtin  = $paramMethod['type']['builtin'] ?? false;
            $optional = $paramMethod['optional'] ?? false;
            $error    = "{$class}@{$method}(... {$type} {$vflag}{$name} ...)";

            if (array_key_exists($name, ($paramsRoute['raw'] ?? []))) {
                $val = $paramsRoute['kv'][$name] ?? null;
                if ($builtin) {
                    if (is_null($val) && (! $optional)) {
                        throw new PortMethodParameterMissingException($error);
                    }
                    try {
                        $val = TypeHint::convert($val, $type);
                    } catch (TypeHintConverterNotExistsException | TypeHintConvertException $e) {
                        $code    = $e->getCode();
                        $result  = [objectname($e), $code, $e->getMessage()];
                        $wraperr = $route['wraperr'] ?? null;
                        $mimeout = $route['mimeout'] ?? null;

                        Response::new()
                        ->setMimeAlias($mimeout)
                        ->setStatus($code)
                        ->send(Response::setWrapperOnResult($result, $wraperr));
                    }
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
