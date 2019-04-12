<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;

final class RouteManager
{
    const ROUTE_DIR = ['Http', 'Port'];
    const AUTONOMY_HANLDER = 'execute';

    private static $aliases = [];
    private static $routes  = [];
    private static $dirs    = [];

    /**
     * Find route definition by given uri, verb and mimes
     *
     * @param string $uri: URL path
     * @param string $method: HTTP verb
     * @param array|null $mimes
     * @return array|bool
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

    /**
     * Compile port classes and assemble formatted routes
     *
     * @param $dirs array: Directories store port classes
     */
    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs    = [];
        self::$routes  = [];
        self::$aliases = [];

        array_map(function ($item) {
            $dir = ospath($item, self::ROUTE_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Excetions may thrown but let invoker to catch for different scenarios
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, $ofProperties, $ofMethods) = $annotations;
                self::assemble($ofClass, $ofProperties, $ofMethods);
            }
        }, __CLASS__);
    }

    /**
     * Assemble routes definitions from class annotations
     *
     * @param array $ofClass: Annotations of class
     * @param array $ofProperties: Annotations of class properties
     * @param array $ofMethod: Annotations of methods
     * @return null
     */
    public static function assemble(array $ofClass, array $ofProperties, array $ofMethods)
    {
        $namespace = $ofClass['namespace'] ?? null;
        $autonomy  = $ofClass['doc']['AUTONOMY'] ?? false;
        $docClass  = $ofClass['doc'] ?? [];
        if ($autonomy) {
            $handler = $ofMethods[self::AUTONOMY_HANLDER] ?? false;
            if (! $handler) {
                exception('AutonomyHandlerNotExists', [
                    'class'   => $namespace,
                    'handler' => self::AUTONOMY_HANLDER,
                ]);
            }
            $handler['doc']['WRAPIN'] = $namespace;
            self::add($namespace, self::AUTONOMY_HANLDER, $docClass, $handler, $ofProperties);
            return;
        }

        $ofMethods = $ofMethods ?? [];
        foreach ($ofMethods as $method => $ofMethod) {
            self::add($namespace, $method, $docClass, $ofMethod, $ofProperties);
        }
    }

    /**
     * Add one single route
     *
     * @param string $class: the route port class namespace
     * @param string $method: the route port class method
     * @param array $docClass: the annotations from class doc comments
     * @param array $ofMethod: the annotations of a class method
     * @param array $ofProperties: the annotations of class properties
     */
    public static function add(
        string $class,
        string $method,
        array $docClass = [],
        array $ofMethod = [],
        array $ofProperties = []
    ) {
        if (! class_exists($class)) {
            exception('PortClassNotExists', compact('class'));
        }
        if (! method_exists($class, $method)) {
            exception('PortMethodNotExists', compact('class', 'method'));
        }
        $attrs = $ofMethod['doc'] ?? [];
        if (($attrs['NOTROUTE'] ?? false) || ($docClass['NOTROUTE'] ?? false)) {
            return;
        }

        $globalRoute   = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.route', '');
        $globalPipein  = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.pipein', []);
        $globalPipeout = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.pipeout', []);
        $globalWrapin  = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.wrapin', null);
        $globalWrapout = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.wrapout', null);
        $globalWraperr = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.wraperr', null);

        $defaultRoute   = $docClass['ROUTE']   ?? null;
        $defaultVerbs   = $docClass['VERB']    ?? [];
        $defaultSuffix  = $docClass['SUFFIX']  ?? [];
        $defaultMimein  = $docClass['MIMEIN']  ?? null;
        $defaultMimeout = $docClass['MIMEOUT'] ?? null;
        $defaultWrapin  = $docClass['WRAPIN']  ?? $globalWrapin;
        $defaultWrapout = $docClass['WRAPOUT'] ?? $globalWrapout;
        $defaultWraperr = $docClass['WRAPERR'] ?? $globalWraperr;
        $defaultAssembler = $docClass['ASSEMBLER'] ?? null;
        $defaultPipein    = $docClass['PIPEIN']    ?? [];
        $defaultPipeout   = $docClass['PIPEOUT']   ?? [];
        $defaultNoPipein  = $docClass['NOPIPEIN']  ?? [];
        $defaultNoPipeout = $docClass['NOPIPEOUT'] ?? [];
        $defaultArguments = $docClass['ARGUMENT']  ?? [];

        $route   = $attrs['ROUTE']   ?? null;
        $alias   = $attrs['ALIAS']   ?? null;
        $mimein  = $attrs['MIMEIN']  ?? $defaultMimein;
        $mimein  = ($mimein === '_')  ? null : $mimein;
        $mimeout = $attrs['MIMEOUT'] ?? $defaultMimeout;
        $mimeout = ($mimeout === '_') ? null : $mimeout;
        $suffix  = $attrs['SUFFIX']  ?? $defaultSuffix;
        $wrapin  = $attrs['WRAPIN']  ?? $defaultWrapin;
        $wrapin  = ($wrapin === '_')  ? null : $wrapin;
        $wrapout = $attrs['WRAPOUT'] ?? $defaultWrapout;
        $wrapout = ($wrapout === '_') ? null : $wrapout;
        $wraperr = $attrs['WRAPERR'] ?? $defaultWraperr;
        $wraperr = ($wraperr === '_') ? null : $wraperr;
        $assembler = $attrs['ASSEMBLER'] ?? $defaultAssembler;
        $assembler = ($assembler === '_') ? null : $assembler;
        $pipein    = $attrs['PIPEIN']    ?? [];
        $pipeout   = $attrs['PIPEOUT']   ?? [];
        $nopipein  = $attrs['NOPIPEIN']  ?? [];
        $nopipeout = $attrs['NOPIPEOUT'] ?? [];
        $arguments = $attrs['ARGUMENT']  ?? [];

        $urlpath = join('/', [$globalRoute, $defaultRoute, $route]);
        list($urlpath, $params) = self::parse($urlpath);
        if (! $urlpath) {
            return;
        }

        $pipeinList    = array_unique(array_merge($globalPipein, $defaultPipein, $pipein));
        $pipeoutList   = array_unique(array_merge($globalPipeout, $defaultPipeout, $pipeout));
        $nopipeinList  = array_unique(array_merge($defaultNoPipein, $nopipein));
        $nopipeoutList = array_unique(array_merge($defaultNoPipeout, $nopipeout));
        $argumentList  = array_merge($defaultArguments, $arguments);

        // Arguments existence check
        $properties = [];
        foreach ($argumentList as $argument => $rules) {
            $property = $ofProperties[$argument] ?? false;
            if (false === $property) {
                exception('PortMethodArgumentNotDefined', compact('argument', 'class', 'method'));
            }

            $doc = $property['doc'] ?? [];
            $doc = array_merge($doc, $rules);
            $property['doc'] = $doc;
            $properties[$argument] = $property;
        }

        $verbs = array_unique(array_merge(($attrs['VERB'] ?? []), $defaultVerbs));
        foreach ($verbs as $verb) {
            self::deduplicate($urlpath, $verb, $alias, $class, $method, true);

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
                'verb'   => $verb,
                'alias'  => $alias,
                'class'  => $class,
                'method' => [
                    'name'   => $method,
                    'params' => $ofMethod['parameters'] ?? [],
                ],
                'pipes' => [
                    'in'    => $pipeinList,
                    'out'   => $pipeoutList,
                    'noin'  => $nopipeinList,
                    'noout' => $nopipeoutList,
                ],
                'params' => [
                    'raw'  => $params,    // Route parameter keys from definition
                    'res'  => [],         // Route parameter values from request uri
                    'api'  => [],         // Route parameters validated
                    'kv'   => [],         // Route parameters valideted as K-V format
                    'pipe' => [],         // Route parameters set by pipes
                ],
                'mimein'    => $mimein,
                'mimeout'   => $mimeout,
                'wrapin'    => $wrapin,
                'wrapout'   => $wrapout,
                'wraperr'   => $wraperr,
                'assembler' => $assembler,
                'arguments' => $properties,
            ];
        }
    }

    /**
     * De-duplicate route and alias definitions
     */
    public static function deduplicate(
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

            exception('DuplicateRouteDefinition', [
                'verb' => $verb,
                'path' => $urlpath,
                'conflict' => [
                    'class'  => $_classns,
                    'method' => $_method,
                ],
                'current' => [
                    'class'  => $classns,
                    'method' => $method,
                ],
            ]);
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

            exception('DuplicateRouteAliasDefinition', [
                'alias' => $alias,
                'conflict' => [
                    'verb'   => $_verb,
                    'path'   => $_urlpath,
                    'class'  => $_classns,
                    'method' => $_method,
                ],
                'previous' => [
                    'verb'   => $verb,
                    'path'   => $urlpath,
                    'class'  => $classns,
                    'method' => $method,
                ],
            ]);
        }
    }

    /**
     * Parse route with route expression and route parameters
     *
     * @param string $route: Raw route from reqeust uri
     * @return array: A list with route expression and route parameters
     */
    public static function parse(string $route) : array
    {
        $route  = explode('/', self::__annotationFilterRoute($route));
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

    public static function __annotationFilterCompatible(string $val) : array
    {
        return array_trim_from_string($val, ',');
    }

    public static function __annotationMultipleMergeCompatible()
    {
        return 'kv';
    }

    public static function __annotationMultipleCompatible() : bool
    {
        return true;
    }

    public static function __annotationFilterArgument(string $val, array $params = []) : array
    {
        $argvs = array_trim_from_string($val, ',');
        $data  = [];
        foreach ($argvs as $name) {
            $data[$name] = $params;
        }

        return $data;
    }

    public static function __annotationMultipleArgument() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeArgument() : bool
    {
        return true;
    }

    public static function __annotationFilterPipein(string $val) : ?string
    {
        return trim($val) ?: null;
    }

    public static function __annotationFilterNopipein(string $val) : ?string
    {
        return trim($val) ?: null;
    }

    public static function __annotationFilterNopipeout(string $val) : ?string
    {
        return trim($val) ?: null;
    }

    public static function __annotationFilterPipeout(string $val) : ?string
    {
        return trim($val) ?: null;
    }

    public static function __annotationMultipleMergeSuffix() : bool
    {
        return true;
    }

    public static function __annotationMultipleSuffix() : bool
    {
        return true;
    }

    public static function __annotationFilterSuffix(string $val) : array
    {
        return array_trim_from_string(strtolower(trim($val)), ',');
    }

    public static function __annotationFilterVerb(string $val) : array
    {
        return array_trim_from_string(strtoupper(trim($val)), ',');
    }

    public static function __annotationFilterRoute(string $val)
    {
        $arr = array_trim(explode('/', trim($val)));

        return empty($arr) ? '/' : join('/', $arr);
    }

    public static function __annotationMultipleVerb() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeVerb() : bool
    {
        return true;
    }

    public static function __annotationMultiplePipeout() : bool
    {
        return true;
    }

    public static function __annotationMultiplePipein() : bool
    {
        return true;
    }

    public static function __annotationMultipleNopipein() : bool
    {
        return true;
    }

    public static function __annotationMultipleNopipeout() : bool
    {
        return true;
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
