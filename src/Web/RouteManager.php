<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Facade\Annotation;
use Loy\Framework\Base\Exception\DuplicateRouteDefinitionException;
use Loy\Framework\Base\Exception\DuplicateRouteAliasDefinitionException;

final class RouteManager
{
    const ROUTE_DIR = ['Http', 'Port'];

    private static $aliases = [];
    private static $routes  = [];
    private static $dirs    = [];

    /**
     * Find route definition by given uri, verb and mimes
     */
    public static function find(string $uri = null, string $method = null, ?array $mimes = [])
    {
        if ((! $uri) || (! $method)) {
            return false;
        }
        $route = self::$routes[$uri][$method] ?? false;
        if ($route) {
            return $route;
        }
        $hasSuffix = false;
        foreach ($mimes as $alias) {
            $_length = mb_strlen($uri);
            if (false === $_length) {
                continue;
            }
            $_alias = ".{$alias}";
            $length = mb_strlen($_alias);
            if (false === $length) {
                continue;
            }
            $suffix = mb_substr($uri, -$length, $length);
            if ($suffix === $_alias) {
                $hasSuffix = $alias;
                $uri   = mb_substr($uri, 0, ($_length - $length));
                $uri   = join('/', array_filter(explode('/', $uri)));
                $route = self::$routes[$uri][$method] ?? false;
                if (! $route) {
                    break;
                }
                if (in_array($alias, ($route['suffix']['allow'] ?? []))) {
                    $route['suffix']['current'] = $alias;
                    return $route;
                }

                return false;
            }
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
            if (! $route) {
                continue;
            }
            if ($hasSuffix) {
                if (! in_array($hasSuffix, ($route['suffix']['allow'] ?? []))) {
                    return false;
                }
                $route['suffix']['current'] = $hasSuffix;
            }

            $params = $route['params']['raw'] ?? [];
            if (count($params) === count($replaced)) {
                $params   = array_keys($params);
                $replaced = array_reverse($replaced);
                $route['params']['kv']  = array_combine($params, $replaced);
                $route['params']['res'] = $replaced;
            }
            return $route;
        }

        return false;
    }

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = ospath($item, self::ROUTE_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Excetions may thrown but let invoker to catch for different scenarios
        //
        // use Loy\Framework\Base\Exception\InvalidAnnotationDirException;
        // use Loy\Framework\Base\Exception\InvalidAnnotationNamespaceException;
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, $ofProperties, $ofMethods) = $annotations;
                self::assembleRoutesFromAnnotations($ofClass, $ofMethods);
            }
        }, __CLASS__);
    }

    public static function assembleRoutesFromAnnotations(array $ofClass, array $ofMethods)
    {
        $classNamespace = $ofClass['namespace']      ?? null;
        $routePrefix    = $ofClass['doc']['ROUTE']   ?? null;
        $middlewares    = $ofClass['doc']['PIPE']    ?? [];
        $defaultVerbs   = $ofClass['doc']['VERB']    ?? [];
        $defaultSuffix  = $ofClass['doc']['SUFFIX']  ?? [];
        $defaultMimein  = $ofClass['doc']['MIMEIN']  ?? null;
        $defaultMimeout = $ofClass['doc']['MIMEOUT'] ?? null;
        $defaultWrapin  = $ofClass['doc']['WRAPIN']  ?? null;
        $defaultWrapout = $ofClass['doc']['WRAPOUT'] ?? null;
        $defaultWraperr = $ofClass['doc']['WRAPERR'] ?? null;

        $ofMethods = $ofMethods['self'] ?? [];
        foreach ($ofMethods as $method => $_attrs) {
            $attrs = $_attrs['doc'] ?? [];
            $notroute = $attrs['NOTROUTE'] ?? false;
            $route = $attrs['ROUTE'] ?? '';
            if ($notroute || (! $route)) {
                continue;
            }
            $alias   = $attrs['ALIAS']   ?? null;
            $verbs   = $attrs['VERB']    ?? $defaultVerbs;
            $mimein  = $attrs['MIMEIN']  ?? $defaultMimein;
            $mimein  = ($mimein === '_') ? null : $mimein;
            $mimeout = $attrs['MIMEOUT'] ?? $defaultMimeout;
            $mimeout = ($mimeout === '_') ? null : $mimeout;
            $wrapin  = $attrs['WRAPIN']  ?? $defaultWrapin;
            $wrapin  = ($wrapin === '_') ? null : $wrapin;
            $wrapout = $attrs['WRAPOUT'] ?? $defaultWrapout;
            $wrapout = ($wrapout === '_') ? null : $wrapout;
            $wraperr = $attrs['WRAPERR'] ?? $defaultWraperr;
            $wraperr = ($wraperr === '_') ? null : $wraperr;
            $suffix  = $attrs['SUFFIX']  ?? $defaultSuffix;
            $middles = $attrs['PIPE'] ?? [];
            $middles = array_unique(array_merge($middlewares, $middles));
            $urlpath = $routePrefix ? join('/', [$routePrefix, $route]) : $route;
            list($urlpath, $params) = self::parseRoute($urlpath);
            foreach ($verbs as $verb) {
                self::validateDuplication($urlpath, $verb, $alias, $classNamespace, $method, true);

                if ($alias) {
                    self::$aliases[$alias] = [
                        'urlpath' => $urlpath,
                        'verb'    => $verb,
                    ];
                }
                self::$routes[$urlpath][$verb] = [
                    'urlpath' => $urlpath,
                    'suffix'  => [
                        'allow'   => $suffix,
                        'current' => null,
                    ],
                    'verb'    => $verb,
                    'alias'   => $alias,
                    'class'   => $classNamespace,
                    'method'  => [
                        'name'   => $method,
                        'params' => $_attrs['parameters'] ?? [],
                    ],
                    'pipes'   => $middles,
                    'params'  => [
                        'raw' => $params,
                        'res' => [],
                        'api' => [],
                        'kv'  => [],
                    ],
                    'mimein'  => $mimein,
                    'mimeout' => $mimeout,
                    'wrapin'  => $wrapin,
                    'wrapout' => $wrapout,
                    'wraperr' => $wraperr,
                ];
            }
        }
    }

    public static function validateDuplication(
        string $urlpath,
        string $verb,
        string $alias = null,
        string $classns = null,
        string $method = null,
        bool $exception = false
    ) {
        if ($route = (self::$routes[$urlpath][$verb] ?? false)) {
            $_classns = $route['class'] ?? '?';
            $_method  = $route['method']['name'] ?? '?';
            if (! $exception) {
                return false;
            }

            throw new DuplicateRouteDefinitionException("{$verb} {$urlpath} ({$classns}@{$method}) <=> {$_classns}@{$_method}");
        }

        if ($alias && ($_alias = (self::$aliases[$alias] ?? false))) {
            if (! $exception) {
                return false;
            }
            $_urlpath = $_alias['urlpath'] ?? '?';
            $_verb    = $_alias['verb']    ?? '?';
            $_route   = self::$routes[$_urlpath][$_verb] ?? [];
            $_classns = $_route['class']   ?? '?';
            $_method  = $_route['method']['name'] ?? '?';
            throw new DuplicateRouteAliasDefinitionException(
                "{$alias} => ({$verb} {$urlpath} | {$classns}@{$method}) <=> ({$_verb} {$_urlpath} | {$_classns}@{$_method})"
            );
        }
    }

    public static function parseRoute(string $route) : array
    {
        $route  = self::filterAnnotationRoute($route, true);
        $params = [];
        array_walk($route, function (&$val, $key) use (&$params) {
            $matches = [];
            if (1 === preg_match('#{([a-z]\w+)}#', $val, $matches)) {
                if ($_param = ($matches[1] ?? false)) {
                    $params[$_param] = null;
                    $val = '?';
                }
            }
        });
       
        $route = $route ? join('/', $route) : '/';

        return [$route, $params];
    }

    public static function filterAnnotationPipe(string $val) : array
    {
        return array_trim(explode(',', trim($val)));
    }

    public static function filterAnnotationSuffix(string $val) : array
    {
        return array_trim(explode(',', strtolower(trim($val))));
    }

    public static function filterAnnotationVerb(string $val) : array
    {
        return array_trim(explode(',', strtoupper(trim($val))));
    }

    public static function filterAnnotationRoute(string $val, bool $array = false)
    {
        $arr = array_trim(explode('/', trim($val)));

        if ($array) {
            return $arr;
        }

        return empty($arr) ? '/' : join('/', $arr);
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
