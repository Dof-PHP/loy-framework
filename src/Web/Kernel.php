<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Exception;
use Error;
use Loy\Framework\Core\DomainManager;
use Loy\Framework\Core\Exception\InvalidProjectRootException;
use Loy\Framework\Web\RouteManager;
use Loy\Framework\Web\Request;
use Loy\Framework\Web\Response;
use Loy\Framework\Web\Route;
use Loy\Framework\Web\Exception\RouteNotExistsException;
use Loy\Framework\Web\Exception\InvalidRequestMimeException;
use Loy\Framework\Web\Exception\BadHttpPortCallException;
use Loy\Framework\Web\Exception\PortNotExistException;
use Loy\Framework\Web\Exception\PortMethodNotExistException;
use Loy\Framework\Web\Exception\PortMethodParameterMissingException;
use Loy\Framework\Web\Exception\BrokenHttpPortMethodDefinitionException;

final class Kernel
{
    const DOMAIN_DIR = 'domain';

    private static $projectRoot = null;

    public static function handle(string $projectRoot)
    {
        if (! is_dir($projectRoot)) {
            throw new InvalidProjectRootException($projectRoot);
        }
        self::$projectRoot = $projectRoot;

        self::compileDomains();
        self::compileRoutes();
        self::compilePipes();
        self::processRequest();
    }

    public static function compileDomains()
    {
        $domainRoot = join(DIRECTORY_SEPARATOR, [self::$projectRoot, self::DOMAIN_DIR]);
        DomainManager::compile($domainRoot);
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

        try {
            $params = self::buildPortMethodParameters($route);
            $result = call_user_func_array([$port, $method], $params);

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
            if (array_key_exists($name, ($paramsRoute['raw'] ?? []))) {
                $params[] = $paramsRoute['kv'][$name] ?? null;
                continue;
            }
            if ($paramMethod['optional'] ?? false) {
                break;
            }
            $type  = $paramMethod['type']['type'] ?? false;
            $error = "{$class}@{$method}(... {$type} {$vflag}{$name} ...)";
            if ($paramMethod['type']['builtin'] ?? false) {
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

    public static function getProjectRoot()
    {
        return self::$projectRoot;
    }
}
