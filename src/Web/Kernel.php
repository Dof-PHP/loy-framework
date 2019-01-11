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
use Loy\Framework\Web\Exception\BadHttpPortCallException;
use Loy\Framework\Web\Exception\PortNotExistException;
use Loy\Framework\Web\Exception\PortMethodNotExistException;

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
        self::processRequest();
    }

    public static function compileDomains()
    {
        $domainRoot = join(DIRECTORY_SEPARATOR, [self::$projectRoot, self::DOMAIN_DIR]);
        DomainManager::compile($domainRoot);
    }

    public static function compileRoutes()
    {
        RouteManager::compile(DomainManager::getDomains());
    }

    public static function processRequest()
    {
        $route  = RouteManager::findRouteByUriAndMethod(Request::getUri(), Request::getMethod());
        if ($route === false) {
            throw new RouteNotExistsException("{$method} {$uri}");
        }
        Route::setData($route);

        $class  = $route['class']  ?? '-';
        $method = $route['method'] ?? '-';
        $params = $route['params']['res'] ?? [];

        if (! class_exists($class)) {
            throw new PortNotExistException($class);
        }
        $port = new $class;
        if (! method_exists($port, $method)) {
            throw new PortMethodNotExistException("{$class}@{$method}");
        }

        try {
            $result = call_user_func_array([$port, $method], $params);

            Response::setMimeAlias($route['mimeout'] ?? null)->send($result);
        } catch (Exception | Error $e) {
            throw new BadHttpPortCallException("{$class}@{$method}: {$e->getMessage()}");
        }
    }

    public static function getProjectRoot()
    {
        return self::$projectRoot;
    }
}
