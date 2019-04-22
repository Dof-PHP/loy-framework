<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Facade\Annotation;
use Dof\Framework\Facade\Request;

final class RouteManager
{
    const ROUTE_DIR = ['Http', 'Port'];
    const AUTONOMY_HANLDER = 'execute';
    const AUTH_TYPES = ['0', '1', '2', '3'];

    /** @var array: Aliases of route */
    private static $aliases = [];

    /** @var array: Routes definitions */
    private static $routes = [];

    /** @var array: Ports resistant directories */
    private static $dirs = [];

    /** @var array: Data for automate documentation */
    private static $docs = [];

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

    public static function load(array $dirs)
    {
        $cache = Kernel::formatCacheFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$aliases, self::$routes, self::$docs) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_ROUTE_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([self::$dirs, self::$aliases, self::$routes, self::$docs], $cache);
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCacheFile(__CLASS__);
        if (is_file($cache)) {
            unlink($cache);
        }
    }

    /**
     * Compile port classes and assemble formatted routes
     *
     * @param $dirs array: Directories store port classes
     */
    public static function compile(array $dirs, bool $cache = false)
    {
        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs = [];
        self::$docs = [];
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

        if ($cache) {
            array2code([self::$dirs, self::$aliases, self::$routes, self::$docs], Kernel::formatCacheFile(__CLASS__));
        }
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
            if (! ($handler['doc']['TITLE'] ?? null)) {
                $handler['doc']['TITLE'] = $docClass['TITLE'] ?? null;
            }
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
        $author = $attrs['AUTHOR'] ?? ($docClass['AUTHOR'] ?? null);
        if (! $author) {
            exception('MissingPortAuthor', compact('class', 'method'));
        }
        $title = $attrs['TITLE'] ?? null;
        if (! $title) {
            exception('MissingPortMethodTitle', compact('class', 'method'));
        }
        $auth = $attrs['AUTH'] ?? ($docClass['AUTH'] ?? '0');
        if (! in_array($auth, self::AUTH_TYPES)) {
            exception('BadAuthType', compact('class', 'method'));
        }
        $domainKey = DomainManager::getKeyByNamespace($class);
        if (! $domainKey) {
            exception('BadPortClassWithOutDomainKey', compact('class'));
        }
        $domainTitle = ConfigManager::getDomainByNamespace($class, 'domain.title', $domainKey);

        $globalRoute   = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.route', '');
        $globalPipein  = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.pipein', []);
        $globalPipeout = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.pipeout', []);
        $globalWrapin  = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.wrapin', null);
        $globalWrapout = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.wrapout', null);
        $globalWraperr = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.wraperr', null);

        $defaultVersion = $docClass['VERSION'] ?? 'v0';
        $defaultGroup   = $docClass['GROUP']   ?? [];
        $defaultModels  = $docClass['MODEL']   ?? null;
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
        $group   = $attrs['GROUP']   ?? [];
        $version = $attrs['VERSION'] ?? $defaultVersion;
        $models  = $attrs['MODEL']   ?? $defaultModels;
        $models  = $models === '_'   ? null : $models;
        $mimein  = $attrs['MIMEIN']  ?? $defaultMimein;
        $mimein  = $mimein === '_'   ? null : $mimein;
        $mimeout = $attrs['MIMEOUT'] ?? $defaultMimeout;
        $mimeout = $mimeout === '_'  ? null : $mimeout;
        $suffix  = $attrs['SUFFIX']  ?? $defaultSuffix;
        $wrapin  = $attrs['WRAPIN']  ?? $defaultWrapin;
        $wrapin  = $wrapin === '_'   ? null : $wrapin;
        $wrapout = $attrs['WRAPOUT'] ?? $defaultWrapout;
        $wrapout = $wrapout === '_'  ? null : $wrapout;
        $wraperr = $attrs['WRAPERR'] ?? $defaultWraperr;
        $wraperr = $wraperr === '_'  ? null : $wraperr;
        $assembler = $attrs['ASSEMBLER'] ?? $defaultAssembler;
        $pipein    = $attrs['PIPEIN']    ?? [];
        $pipeout   = $attrs['PIPEOUT']   ?? [];
        $nopipein  = $attrs['NOPIPEIN']  ?? [];
        $nopipeout = $attrs['NOPIPEOUT'] ?? [];
        $arguments = $attrs['ARGUMENT']  ?? [];

        $urlpath = join('/', [$globalRoute, $defaultRoute, $route]);
        list($urlpath, $route, $params) = self::parse($urlpath);
        if (! $urlpath || (! $route)) {
            return;
        }

        $pipeinList    = array_unique(array_merge($globalPipein, $defaultPipein, $pipein));
        $pipeoutList   = array_unique(array_merge($globalPipeout, $defaultPipeout, $pipeout));
        $nopipeinList  = array_unique(array_merge($defaultNoPipein, $nopipein));
        $nopipeoutList = array_unique(array_merge($defaultNoPipeout, $nopipeout));
        $argumentList  = array_merge($defaultArguments, $arguments);

        // Arguments existence check
        $properties = [];
        if ($docClass['AUTONOMY'] ?? false) {
            $properties = $ofProperties;
        } else {
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
        }

        $verbs = array_unique(array_merge(($attrs['VERB'] ?? []), $defaultVerbs));
        foreach ($verbs as $verb) {
            self::deduplicate($route, $verb, $alias, $class, $method, true);

            if ($alias) {
                self::$aliases[$alias] = [
                    'urlpath' => $urlpath,
                    'route'   => $route,
                    'verb'    => $verb,
                ];
            }
            self::$routes[$route][$verb] = [
                'urlpath' => $urlpath,
                'suffix'  => [
                    'allow'   => $suffix,
                    'current' => null,
                ],
                'verb'   => $verb,
                'alias'  => $alias,
                'class'  => $class,
                'model'  => $models,
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

        if ($ofMethod['doc']['NODOC'] ?? ($docClass['NODOC'] ?? false)) {
            return;
        }

        $docs = self::$docs[$version][$domainKey] ?? [
            'title' => $domainTitle,
            'group' => [],
            'list'  => [],
        ];
        $params = [];
        $validators = [];
        foreach ($properties as $key => $arg) {
            $_doc = $arg['doc'] ?? [];
            $param = [
                'key'  => $key,
                'name' => $_doc['TITLE'] ?? null,
                'type' => $_doc['TYPE']  ?? null,
                'notes' => $_doc['NOTES'] ?? '',
                'default' => $_doc['DEFAULT'] ?? null,
                'compatibles' => $_doc['COMPATIBLE'] ?? [],
            ];

            array_unset(
                $_doc,
                '__ext__',
                'TITLE',
                'TYPE',
                'NOTES',
                'DEFAULT',
                'COMPATIBLE'
            );

            if ($wrapin = ($_doc['WRAPIN'] ?? false)) {
                $_doc['WRAPIN'] = self::formatDocNamespace($wrapin);
            }
            $param['validators'] = $_doc;
            $params[] = $param;
        }
        $doc = [
            'route' => $urlpath,
            'auth'  => $auth,
            'verbs' => $verbs,
            'title' => $title,
            'models'  => self::formatDocNamespace($models),
            'author'  => $author,
            'version' => $version,
            'params'  => $params,
            'suffixs' => $suffix,
            'headers' => [
                'in'  => $mimein  ? [Request::getMimeByAlias($mimein)]  : [],
                'out' => $mimeout ? [Request::getMimeByAlias($mimeout)] : [],
            ],
        ];

        $groups = array_merge(self::formatDocGroups($defaultGroup), self::formatDocGroups($group));
        if ($groups) {
            $docs['group'] = self::dynamicAppendDocWithGroups($docs['group'] ?? [], $doc, $groups);
        } else {
            $docs['list'][] = $doc;
        }

        self::$docs[$version][$domainKey] = $docs;
    }

    private static function formatDocNamespace(string $namespace = null)
    {
        if (! $namespace) {
            return null;
        }

        $arr = array_trim_from_string($namespace, '\\');
        unset($arr[0]);

        return join('.', $arr);
    }

    private static function formatDocGroups($group)
    {
        if (! $group) {
            return [];
        }
        list($key, $val) = $group;
        $keys = array_trim_from_string($key, '/');
        $vals = array_trim_from_string($val, '/');
        $cntV = count($vals);
        $cntK = count($keys);
        if ($cntK !== $cntV) {
            $_vals = [];
            for ($i = $cntK-1; $i >= 0; $i--) {
                $_vals[] = $vals[$i] ?? ($keys[$cntK-1-$i] ?? 'unknown');
            }
        } else {
            $_vals = $vals;
        }
        return array_combine($keys, $_vals);
    }

    /**
     * Append $append into $data dynamically with $groups
     */
    private static function dynamicAppendDocWithGroups(array $data, $append, array $groups)
    {
        $keys = $groups;
        foreach ($groups as $name => $title) {
            if (false === next($groups)) {
                $data[$name]['title']  = $title;
                $data[$name]['list'][] = $append;
                $data[$name]['group']  = [];
                return $data;
            }

            $_data = $data[$name]['group'] ?? [];
            unset($keys[$name]);
            $data[$name]['title'] = $title;
            $data[$name]['group'] = self::dynamicAppendDocWithGroups($_data, $append, $keys);
            $data[$name]['list']  = [];
            return $data;
        }
    }

    /**
     * De-duplicate route and alias definitions
     */
    public static function deduplicate(
        string $_route,
        string $verb,
        string $alias = null,
        string $classns = null,
        string $method = null,
        bool $exception = false
    ) {
        if ($route = (self::$routes[$_route][$verb] ?? false)) {
            $_urlpath = $route['urlpath'] ?? '?';
            $_classns = $route['class']   ?? '?';
            $_method  = $route['method']['name'] ?? '?';
            if (! $exception) {
                return false;
            }

            exception('DuplicateRouteDefinition', [
                'verb'  => $verb,
                'route' => $_route,
                'conflict' => [
                    'path'   => $_urlpath,
                    'class'  => $_classns,
                    'method' => $_method,
                ],
                'current' => [
                    'class'  => $classns,
                    'path'   => $urlpath,
                    'method' => $method,
                ],
            ]);
        }

        if ($alias && ($_alias = (self::$aliases[$alias] ?? false))) {
            if (! $exception) {
                return false;
            }
            $_urlpath = $_alias['urlpath'] ?? '?';
            $__route  = $_alias['route']   ?? '?';
            $_verb    = $_alias['verb']    ?? '?';
            $_route   = self::$routes[$_urlpath][$_verb] ?? [];
            $_classns = $_route['class']   ?? '?';
            $_method  = $_route['method']['name'] ?? '?';

            exception('DuplicateRouteAliasDefinition', [
                'alias' => $alias,
                'conflict' => [
                    'verb'   => $_verb,
                    'path'   => $_urlpath,
                    'route'  => $__route,
                    'class'  => $_classns,
                    'method' => $_method,
                ],
                'previous' => [
                    'verb'   => $verb,
                    'path'   => $urlpath,
                    'route'  => $_route,
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
        $route   = explode('/', self::__annotationFilterRoute($route));
        $urlpath = $route ? join('/', $route) : '/';
        $params  = [];

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

        return [$urlpath, $route, $params];
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
        $_params = [];
        foreach ($params as $rule => $error) {
            $arr = array_trim_from_string($rule, ':');
            $_rule = $arr[0] ?? false;
            unset($arr[0]);
            $ext = join('', $arr);
            if (! $_rule) {
                continue;
            }
            $ext = $ext ? ":{$ext}": '';
            $_rule = strtoupper($_rule);

            $_params[$_rule.$ext] = $error;
        }

        $argvs  = array_trim_from_string($val, ',');
        $data   = [];
        foreach ($argvs as $name) {
            $data[$name] = $_params;
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

    public static function __annotationFilterModel(string $model, array $ext = [], string $namespace = null)
    {
        $model = trim($model);
        if (! $model) {
            return null;
        }
        if ($model === '_') {
            return $model;
        }
        if (class_exists($model)) {
            return $model;
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingDataModelUseClass', compact('model', 'namespace'));
        }

        $_model = get_annotation_ns($model, $namespace);
        if ((! $_model) || (! class_exists($_model))) {
            exception('DataModelNotExists', compact('model', 'namespace'));
        }

        return $_model;
    }

    public static function __annotationFilterVerb(string $val) : array
    {
        return array_trim_from_string(strtoupper(trim($val)), ',');
    }

    public static function __annotationFilterWrapout(string $wrapout, array $ext = [], string $namespace = null)
    {
        $wrapout = trim($wrapout);
        if (! $wrapout) {
            return null;
        }
        if ($wrapout === '_') {
            return $wrapout;
        }
        if (class_exists($wrapout)) {
            return $wrapout;
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingWrapOutUseClass', compact('wrapout', 'namespace'));
        }

        $_wrapout = get_annotation_ns($wrapout, $namespace);
        if ((! $_wrapout) || (! class_exists($_wrapout))) {
            exception('WrapperOutNotExists', compact('wrapout', 'namespace'));
        }

        return $_wrapout;
    }

    public static function __annotationFilterWraperr(string $wraperr, array $ext = [], string $namespace = null)
    {
        $wraperr = trim($wraperr);
        if (! $wraperr) {
            return null;
        }
        if ($wraperr === '_') {
            return $wraperr;
        }
        if (class_exists($wraperr)) {
            return $wraperr;
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingWrapErrUseClass', compact('wraperr', 'namespace'));
        }

        $_wraperr = get_annotation_ns($wraperr, $namespace);
        if ((! $_wraperr) || (! class_exists($_wraperr))) {
            exception('WrapErrNotExists', compact('wraperr', 'namespace'));
        }

        return $_wraperr;
    }

    public static function __annotationFilterWrapin(string $wrapin, array $ext = [], string $namespace = null)
    {
        $wrapin = trim($wrapin);
        if (! $wrapin) {
            return null;
        }
        if ($wrapin === '_') {
            return $wrapein;
        }
        if (class_exists($wrapin)) {
            return $wrapin;
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingWrapInUseClass', compact('wrapin', 'namespace'));
        }

        $_wrapin = get_annotation_ns($wrapin, $namespace);
        if ((! $_wrapin) || (! class_exists($_wrapin))) {
            exception('WrapperInNotExists', compact('wrapin', 'namespace'));
        }
        if ($_wrapin === $namespace) {
            exception('WrapInEqualsToUseClass', compact('wrapin', '_wrapin', 'namespace'));
        }

        return $_wrapin;
    }

    public static function __annotationFilterGroup(string $group, array $params = [])
    {
        $title = $params['title'] ?? (array_keys($params)[0] ?? null);

        return [trim(strtolower($group)), $title];
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

    public static function getDirs() : array
    {
        return self::$dirs;
    }

    public static function getDocs() : array
    {
        return self::$docs;
    }
}
