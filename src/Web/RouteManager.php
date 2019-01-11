<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Exception;
use ReflectionClass;
use ReflectionException;
use Loy\Framework\Web\Exception\InvalidRouteDirException;
use Loy\Framework\Web\Exception\InvalidHttpPortNamespaceException;
use Loy\Framework\Web\Exception\DuplicateRouteDefinitionException;
use Loy\Framework\Web\Exception\DuplicateRouteAliasDefinitionException;

final class RouteManager
{
    const ROUTE_DIR = 'Port/Http';
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

        self::$dirs = $dirs;

        foreach ($dirs as $dir) {
            $routeDir = join('/', [$dir, self::ROUTE_DIR]);

            if (! is_dir($routeDir)) {
                throw new InvalidRouteDirException($routeDir);
            }

            self::compileHttpPortDir($routeDir);
        }
    }

    public static function compileHttpPortDir(string $dir)
    {
        walk_dir($dir, function ($path) {
            if ($path->isFile() && ('php' === $path->getExtension())) {
                self::compileHttpPortFile($path->getRealpath());
                return;
            }

            if ($path->isDir()) {
                self::compileHttpPortDir($path->getRealpath());
                return;
            }
        });
    }

    public static function compileHttpPortFile(string $path)
    {
        $ns = get_namespace_of_file($path, true);
        if (! class_exists($ns)) {
            throw new InvalidHttpPortNamespaceException($ns);
        }

        $annotations = self::parseAnnotationsByNamespace($ns);
        if ($annotations) {
            list($ofClass, $ofMethods) = $annotations;
            self::assembleRoutesFromAnnotations($ofClass, $ofMethods);
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
            $route   = $attrs['ROUTE']  ?? '';
            $alias   = $attrs['ALIAS']  ?? null;
            $verbs   = $attrs['VERB'] ?? [];
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
                if ($alias && (self::$aliases[$alias] ?? false)) {
                    throw new DuplicateRouteAliasDefinitionException("{$alias} => {$verb} {$urlpath} ({$classNamespace}@{$method})");
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
                    'method'  => $method,
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

    public static function parseAnnotationFromSingleComment(string $comment) : array
    {
        if (! $comment) {
            return [];
        }

        $arr = explode(PHP_EOL, $comment);
        foreach ($arr as $line) {
            $matches = [];
            if (1 === preg_match(self::REGEX, $line, $matches)) {
                $key = $matches[1] ?? false;
                $val = $matches[2] ?? false;
                if ((! $key) || (! $val)) {
                    continue;
                }
                $filter = 'parseString'.ucfirst(strtolower($key));
                if (method_exists(__CLASS__, $filter)) {
                    $val = self::$filter($val);
                }
                $res[strtoupper($key)] = $val;
            }
        }

        return $res;
    }

    public static function parseStringMimeOut(string $val) : string
    {
        return trim($val);
    }

    public static function parseStringMimein(string $val) : string
    {
        return trim($val);
    }

    public static function parseStringAlias(string $val) : string
    {
        return trim($val);
    }

    public static function parseStringPipe(string $val) : array
    {
        return array_trim(explode(',', trim($val)));
    }

    public static function parseStringVerb(string $val) : array
    {
        return array_trim(explode(',', strtoupper(trim($val))));
    }

    public static function parseStringRoute(string $val)
    {
        return join('/', array_trim(explode('/', trim($val))));
    }

    public static function parseAnnotationsByNamespace(string $namespace) : array
    {
        try {
            $reflector = new ReflectionClass($namespace);
        } catch (ReflectionException $e) {
            return [];
        }

        $classDocComment = $reflector->getDocComment();
        $ofClass = [];
        if (false !== $classDocComment) {
            $ofClass = self::parseAnnotationFromSingleComment($classDocComment);
        }
        $ofClass['namespace'] = $namespace;

        $ofMethods = [];
        $methods   = $reflector->getMethods();
        foreach ($methods as $method) {
            $comment = $method->getDocComment();
            if (false !== $comment) {
                $ofMethods[$method->name] = self::parseAnnotationFromSingleComment($comment);
            }
        }

        return [
            $ofClass,
            $ofMethods,
        ];
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
