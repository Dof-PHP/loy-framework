<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Exception;
use Loy\Framework\Core\Annotation;
use Loy\Framework\Core\Exception\InvalidAnnotationDirException;
use Loy\Framework\Core\Exception\InvalidAnnotationNamespaceException;
use Loy\Framework\Web\Exception\InvalidRouteDirException;
use Loy\Framework\Web\Exception\InvalidHttpPortNamespaceException;
use Loy\Framework\Web\Exception\DuplicateRouteDefinitionException;
use Loy\Framework\Web\Exception\DuplicateRouteAliasDefinitionException;

final class RouteManager
{
    const ROUTE_DIR = 'Http/Port';
    const REGEX = '#@([a-zA-z]+)\((.*)\)#';

    private static $aliases = [];
    private static $routes  = [];
    private static $dirs    = [];

    public static function findRouteByUriAndMethod(string $uri, string $method)
    {
        $route = self::$routes[$uri][$method] ?? false;
        if ($route) {
            return $route;
        }

        $arr = $_arr = array_reverse(explode('/', $uri));
        $cnt = count($arr);
        $set = subsets($arr);
        foreach ($set as $replaces) {
            $arr = $_arr;
            $replaced = [];
            foreach ($replaces as $idx => $replace) {
                $replaced[] = $arr[$idx];
                $arr[$idx] = '?';
            }
            $try = join('/', array_reverse($arr));
            $route = self::$routes[$try][$method] ?? false;
            if ($route) {
                $params = $route['params']['raw'] ?? [];
                if (count($params) === count($replaced)) {
                    $params   = array_keys($params);
                    $replaced = array_reverse($replaced);
                    $route['params']['kv']  = array_combine($params, $replaced);
                    $route['params']['res'] = $replaced;
                }
                return $route;
            }
        }

        return false;
    }

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        self::$dirs = array_map(function ($item) {
            return join(DIRECTORY_SEPARATOR, [$item, self::ROUTE_DIR]);
        }, $dirs);

        try {
            Annotation::parseClassDirs(self::$dirs, self::REGEX, function ($annotations) {
                if ($annotations) {
                    list($ofClass, $ofMethods) = $annotations;
                    self::assembleRoutesFromAnnotations($ofClass, $ofMethods);
                }
            }, __CLASS__);
        } catch (InvalidAnnotationDirException $e) {
            throw new InvalidRouteDirException($e->getMessage());
        } catch (InvalidAnnotationNamespaceException $e) {
            throw new InvalidHttpPortNamespaceException($e->getMessage());
        }
    }

    public static function assembleRoutesFromAnnotations(array $ofClass, array $ofMethods)
    {
        $classNamespace = $ofClass['namespace'] ?? '?';
        $routePrefix    = $ofClass['ROUTE']   ?? '';
        $defaultVerbs   = $ofClass['VERB']    ?? [];
        $defaultMimein  = $ofClass['MIMEIN']  ?? null;
        $defaultMimeout = $ofClass['MIMEOUT'] ?? null;
        $middlewares    = $ofClass['PIPE']    ?? [];

        foreach ($ofMethods as $method => $attrs) {
            $notroute = $attrs['NOTROUTE'] ?? false;
            if ($notroute) {
                continue;
            }
            $route   = $attrs['ROUTE']  ?? '';
            $alias   = $attrs['ALIAS']  ?? null;
            $verbs   = $attrs['VERB']   ?? [];
            $params  = [];
            if (! $verbs) {
                $verbs = $defaultVerbs;
            }
            $mimein  = $attrs['MIMEIN']  ?? null;
            if (! $mimein) {
                $mimein  = $defaultMimein;
            }
            $mimeout = $attrs['MIMEOUT'] ?? null;
            if (! $mimeout) {
                $mimeout = $defaultMimeout;
            }

            $middles = $attrs['PIPE'] ?? [];
            $middles = array_unique(array_merge($middlewares, $middles));
            $urlpath = join('/', [$routePrefix, $route]);
            $urlpath = array_filter(explode('/', $urlpath));
            array_walk($urlpath, function (&$val, $key) use (&$params) {
                $matches = [];
                if (1 === preg_match('#{([a-z]\w+)}#', $val, $matches)) {
                    if ($_param = ($matches[1] ?? false)) {
                        $params[$_param] = null;
                        $val = '?';
                    }
                }
            });
            $urlpath = join('/', $urlpath);
            foreach ($verbs as $verb) {
                if (self::$routes[$urlpath][$verb] ?? false) {
                    throw new DuplicateRouteDefinitionException("{$verb} {$urlpath} ({$classNamespace}@{$method})");
                    continue;
                }
                if ($alias && ($_alias = (self::$aliases[$alias] ?? false))) {
                    $_urlpath = $_alias['urlpath'] ?? '?';
                    $_verb    = $_alias['verb']    ?? '?';
                    $_route   = self::$routes[$_urlpath][$_verb] ?? [];
                    $_classns = $_route['class']   ?? '?';
                    $_method  = $_route['method']  ?? '?';
                    throw new DuplicateRouteAliasDefinitionException(
                        "{$alias} => ({$verb} {$urlpath} | {$classNamespace}@{$method}) <=> ({$_verb} {$_urlpath} | {$_classns}@{$_method})"
                    );
                }

                if ($alias) {
                    self::$aliases[$alias] = [
                        'urlpath' => $urlpath,
                        'verb'    => $verb,
                    ];
                }

                self::$routes[$urlpath][$verb] = [
                    'urlpath' => $urlpath,
                    'verb'    => $verb,
                    'alias'   => $alias,
                    'class'   => $classNamespace,
                    'method'  => [
                        'name'   => $method,
                        'params' => $attrs['parameters'] ?? [],
                    ],
                    'pipes'   => $middles,
                    'params'  => [
                        'raw' => $params,
                    ],
                    'mimein'  => $mimein,
                    'mimeout' => $mimeout,
                ];
            }
        }
    }

    public static function filterAnnotationPipe(string $val) : array
    {
        return array_trim(explode(',', trim($val)));
    }

    public static function filterAnnotationVerb(string $val) : array
    {
        return array_trim(explode(',', strtoupper(trim($val))));
    }

    public static function filterAnnotationRoute(string $val)
    {
        return join('/', array_trim(explode('/', trim($val))));
    }

    public static function getAliases() : array
    {
        return self::$aliases;
    }

    public static function getRoutes() : array
    {
        return self::$routes;
    }
}
