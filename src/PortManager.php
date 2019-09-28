<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Facade\Annotation;
use Dof\Framework\Facade\Request;
use Dof\Framework\OFB\Pipe\Sorting;
use Dof\Framework\DDD\Repository;

final class PortManager
{
    const PORT_DIR = ['Http', 'Port'];
    const PIPE_DIR = ['Http', 'Pipe'];
    const WRAPIN_DIR = ['Http', 'WrapIn'];
    const AUTH_TYPES = ['0', '1', '2', '3'];
    const AUTONOMY_HANLDER = 'execute';
    const HTTP_VERBS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var array: Ports resistant directories */
    private static $dirs = [];

    /** @var array: Port definitions (One port can own multiple routes) */
    private static $ports = [];

    /** @var array: Routes basic definitions (One route only belongs to one port) */
    private static $routes = [];

    /** @var array: Aliases of route */
    private static $aliases = [];

    /** @var array: Data for documentation automating */
    private static $docs = [];

    /**
     * - Find definitions of current route by request uri, verb and mimes
     * - Set uripath, suffix, params (etc) for route
     *
     * @param string $uri: Request URL path
     * @param string $method: HTTP verb
     * @param array|null $mimes
     * @return array|null
     */
    public static function find(string $uri = null, string $method = null, ?array $mimes = []) : ?array
    {
        if ((! $uri) || (! $method)) {
            return null;
        }
        $route = self::$routes[$uri][$method] ?? null;
        if ($route) {
            $route['uripath'] = $uri;

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
                if (! ($port = self::get($route))) {
                    break;
                }
                if (in_array($alias, ($port['suffix'] ?? []))) {
                    $route['suffix']  = $alias;
                    $route['uripath'] = $uri;

                    return $route;
                }

                return null;
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
            if (! ($port = self::get($route))) {
                break;
            }
            if ($hasSuffix) {
                if (! in_array($hasSuffix, ($port['suffix'] ?? []))) {
                    return null;
                }
                $route['suffix'] = $hasSuffix;
            }

            $params = $route['params']['raw'] ?? [];
            if (count($params) === count($replaced)) {
                $params   = array_keys($params);
                $replaced = array_reverse($replaced);
                $route['params']['kv']  = array_combine($params, $replaced);
                $route['params']['res'] = $replaced;
            }

            $route['uripath'] = $uri;

            return $route;
        }

        return null;
    }

    public static function load(array $dirs)
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$ports, self::$routes, self::$aliases, self::$docs) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_PORT_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([
                self::$dirs,
                self::$ports,
                self::$routes,
                self::$aliases,
                self::$docs
            ], $cache);
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
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
        // Reset
        self::$dirs = [];
        self::$docs = [];
        self::$ports = [];
        self::$routes  = [];
        self::$aliases = [];

        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = ospath($item, self::PORT_DIR);
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
            array2code([
                self::$dirs,
                self::$ports,
                self::$routes,
                self::$aliases,
                self::$docs
            ], Kernel::formatCompileFile(__CLASS__));
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
        $domainKey = DomainManager::getKeyByNamespace($class);
        if (! $domainKey) {
            exception('BadPortClassWithOutDomainKey', compact('class'));
        }
        $attrs = $ofMethod['doc'] ?? [];
        if ($attrs['NOTROUTE'] ?? false) {
            return;
        }
        if (($docClass['NOTROUTE'] ?? false) && (($attrs['NOTROUTE'] ?? false) !== '0')) {
            return;
        }
        $nodoc  = $attrs['NODOC'] ?? ($docClass['NODOC'] ?? false);
        $author = $attrs['AUTHOR'] ?? ($docClass['AUTHOR'] ?? null);
        if ((! $nodoc) && (! $author)) {
            exception('MissingPortAuthor', compact('class', 'method'));
        }
        $title = $attrs['TITLE'] ?? null;
        if ((! $nodoc) && (! $title)) {
            exception('MissingPortMethodTitle', compact('class', 'method'));
        }
        $subtitle = $attrs['SUBTITLE'] ?? ($docClass['SUBTITLE'] ?? null);
        $auth = $attrs['AUTH'] ?? ($docClass['AUTH'] ?? '0');

        $domainTitle   = ConfigManager::getDomainByNamespace($class, 'domain.title', $domainKey);
        $globalRoute   = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.route', null);
        $globalWrapin  = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.wrapin', null);
        $globalWrapout = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.wrapout', null);
        $globalWraperr = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.wraperr', null);
        $globalPipein  = self::formatPipes(ConfigManager::getDomainMergeDomainByNamespace($class, 'http.port.pipein', []));
        $globalPipeout = self::formatPipes(ConfigManager::getDomainMergeDomainByNamespace($class, 'http.port.pipeout', []));
        $globalHeaderIn  = ConfigManager::getDomainMergeDomainByNamespace($class, 'http.port.headerin', []);
        $globalHeaderOut = ConfigManager::getDomainMergeDomainByNamespace($class, 'http.port.headerout', []);
        $globalHeaderStatus = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.headerstatus', []);
        $globalLogging = ConfigManager::getDomainFinalDomainByNamespace($class, 'http.port.logging', null);
        $globalLogMaskKey = ConfigManager::getDomainFinalDomainByNamespace($class, 'HTTP_REQUEST_PARAMS_MASK_KEYS', ['password', 'secret']);

        $defaultVersion = $docClass['VERSION'] ?? null;
        $defaultInfoOK  = $docClass['INFOOK']  ?? null;
        $defaultCodeOK  = $docClass['CodeOK']  ?? null;
        $defaultRemark  = $docClass['REMARK']  ?? ($docClass['NOTES'] ?? null);
        $defaultStatus  = $docClass['STATUS']  ?? null;
        $defaultGroup   = $docClass['GROUP']   ?? [];
        $defaultModel   = $docClass['MODEL']   ?? null;
        $defaultRoute   = $docClass['ROUTE']   ?? null;
        $defaultVerbs   = $docClass['VERB']    ?? [];
        $defaultSuffix  = $docClass['SUFFIX']  ?? [];
        $defaultMimein  = $docClass['MIMEIN']  ?? null;
        $defaultMimeout = $docClass['MIMEOUT'] ?? null;
        $defaultWrapin  = $docClass['WRAPIN']  ?? $globalWrapin;
        $defaultWrapin  = (($docClass['WRAPIN'] ?? null) === '') ? null : $defaultWrapin;
        $defaultWrapout = $docClass['WRAPOUT'] ?? $globalWrapout;
        $defaultWrapout = (($docClass['WRAPOUT'] ?? null) === '_') ? null : $defaultWrapout;
        $defaultWraperr = $docClass['WRAPERR'] ?? $globalWraperr;
        $defaultWraperr = (($docClass['WRAPERR'] ?? null) === '_') ? null : $defaultWraperr;
        $defaultAssembler = $docClass['ASSEMBLER'] ?? null;
        $defaultPipein    = self::formatPipes($docClass['PIPEIN'] ?? []);
        $defaultPipeout   = self::formatPipes($docClass['PIPEOUT'] ?? []);
        $defaultNoPipein  = self::formatPipes($docClass['NOPIPEIN'] ?? []);
        $defaultNoPipeout = self::formatPipes($docClass['NOPIPEOUT'] ?? []);
        $defaultArguments = $docClass['ARGUMENT']  ?? [];
        $defaultHeaderIn  = $docClass['HEADERIN']  ?? [];
        $defaultHeaderOut = $docClass['HEADEROUT'] ?? [];
        $defaultHeaderStatus = $docClass['HEADERSTATUS'] ?? $globalHeaderStatus;
        $defaultLogging = $docClass['LOGGING'] ?? $globalLogging;
        $defaultLogging = (($docClass['LOGGING'] ?? null) === '_') ? null : $defaultLogging;
        $defaultLogMaskKey = $docClass['LOGMASKKEY'] ?? [];
        $defaultNoDump = $docClass['NODUMP'] ?? null;

        $route   = $attrs['ROUTE']   ?? null;
        $alias   = $attrs['ALIAS']   ?? null;
        $group   = $attrs['GROUP']   ?? [];
        $status  = $attrs['STATUS']  ?? $defaultStatus;
        $version = $attrs['VERSION'] ?? $defaultVersion;
        $infoOK  = $attrs['INFOOK']  ?? $defaultInfoOK;
        $codeOK  = $attrs['CODEOK']  ?? $defaultCodeOK;
        $model   = $attrs['MODEL']   ?? $defaultModel;
        $model   = (($attrs['MODEL'] ?? null) === '_') ? null : $model;
        $mimein  = $attrs['MIMEIN']  ?? $defaultMimein;
        $mimein  = (($attrs['MIMEIN'] ?? null) === '_') ? null : $mimein;
        $mimeout = $attrs['MIMEOUT'] ?? $defaultMimeout;
        $mimeout = (($attrs['MIMEOUT'] ?? null) === '_') ? null : $mimeout;
        $suffix  = $attrs['SUFFIX']  ?? $defaultSuffix;
        $wrapin  = $attrs['WRAPIN']  ?? $defaultWrapin;
        $wrapin  = (($attrs['WRAPIN'] ?? null)=== '_') ? null : $wrapin;
        $wrapout = $attrs['WRAPOUT'] ?? $defaultWrapout;
        $wrapout = (($attrs['WRAPOUT'] ?? null) === '_') ? null : $wrapout;
        $wraperr = $attrs['WRAPERR'] ?? $defaultWraperr;
        $wraperr = (($attrs['WRAPERR'] ?? null) === '_') ? null : $wraperr;
        $assembler = $attrs['ASSEMBLER'] ?? $defaultAssembler;
        $assembler = (($attrs['ASSEMBLER'] ?? null) === '_') ? null : $assembler;
        $headerIn  = $attrs['HEADERIN']  ?? [];
        $headerOut = $attrs['HEADEROUT'] ?? [];
        $headerStatus = $attrs['HEADERSTATUS'] ?? $defaultHeaderStatus;
        $pipein    = $attrs['PIPEIN'] ?? [];
        $pipeout   = $attrs['PIPEOUT'] ?? [];
        $nopipein  = $attrs['NOPIPEIN'] ?? [];
        $nopipeout = $attrs['NOPIPEOUT'] ?? [];
        $arguments = $attrs['ARGUMENT'] ?? [];
        $logging = $attrs['LOGGING'] ?? $defaultLogging;
        $logging = (($attrs['LOGGING'] ?? null) === '_') ? null : $logging;
        $logMaskKey = $attrs['LOGMASKKEY'] ?? [];
        $remark = ($attrs['REMARK'] ?? ($attrs['NOTES'] ?? null)) ?? $defaultRemark;
        $nodump = $attrs['NODUMP'] ?? $defaultNoDump;

        // Decide version prefix in route definition
        $_version = $version[0] ?? null;
        if ('0' == ($version[1]['ROUTE'] ?? 1)) {
            $_version = null;
        }

        $extends = $attrs['__ext__']['ROUTE']['extends'] ?? '1';
        if ($extends === '0') {
            $urlpath = join('/', [$_version, $globalRoute, $route]);
        } else {
            $urlpath = join('/', [$_version, $globalRoute, $defaultRoute, $route]);
        }

        list($urlpath, $route, $params) = self::parse($urlpath);
        if ((! $urlpath) || (! $route)) {
            return;
        }

        $headerInList  = array_unique_merge($globalHeaderIn, $defaultHeaderIn, $headerIn);
        $headerOutList = array_unique_merge($globalHeaderOut, $defaultHeaderOut, $headerOut);
        $logMaskKeys   = array_unique_merge($globalLogMaskKey, $defaultLogMaskKey, $logMaskKey);
        $pipeinList    = array_merge($globalPipein, $defaultPipein, $pipein);
        $pipeoutList   = array_merge($globalPipeout, $defaultPipeout, $pipeout);
        $nopipeinList  = array_merge($defaultNoPipein, $nopipein);
        $nopipeoutList = array_merge($defaultNoPipeout, $nopipeout);
        $argumentList  = array_merge($defaultArguments, $arguments);

        // Arguments existence check
        $properties = [];
        if ($docClass['AUTONOMY'] ?? false) {
            $properties = $ofProperties;
        } else {
            foreach ($argumentList as $argument => $rules) {
                $property = $ofProperties[$argument] ?? false;
                if (false === $property) {
                    exception('ArgumentOfPortMethodNotDefined', compact('argument', 'class', 'method'));
                }

                $doc = $property['doc'] ?? [];
                $doc = array_merge($doc, $rules);
                $property['doc'] = $doc;
                $properties[$argument] = $property;
            }
        }

        $verbs = array_unique_merge(($attrs['VERB'] ?? []), $defaultVerbs);

        // Formatting ports data
        self::$ports[$class][$method] = [
            'class'  => $class,
            'method' => $method,
            'title'  => $title,
            'remark' => $remark,
            'infook' => $infoOK,
            'codeok' => $codeOK,
            'model'  => $model,
            'author' => $author,
            'suffix' => $suffix,
            'mimein' => $mimein,
            'mimeout' => $mimeout,
            'wrapin'  => $wrapin,
            'wrapout' => $wrapout,
            'wraperr' => $wraperr,
            'logging' => $logging,
            'version' => $_version,
            'status'  => $status,
            'pipein'  => $pipeinList,
            'headers' => [
                'in'  => $headerInList,
                'out' => $headerOutList,
                'status' => $headerStatus,
            ],
            'pipeout' => $pipeoutList,
            'subtitle'  => $subtitle,
            'nopipein'  => $nopipeinList,
            'nopipeout' => $nopipeoutList,
            'assembler' => $assembler,
            'argument'  => [],    // Port parameters validated for port method
            'logmaskkey' => $logMaskKeys,    // Port parameters validated for port method
            '__arguments'  => $properties,
            '__parameters' => $ofMethod['parameters'] ?? [],
            '__docext' => $ofMethod['doc']['__ext__'] ?? [],
        ];

        // Formatting routes and alias data
        foreach ($verbs as $verb) {
            self::deduplicate($route, $verb, $urlpath, $class, $method, $alias, true);

            if ($alias) {
                self::$aliases[$alias] = [
                    'route'   => $route,
                    'verb'    => $verb,
                    'class'   => $class,
                    'alias'   => $alias,
                    'method'  => $method,
                    'urlpath' => $urlpath,
                ];
            }
            self::$routes[$route][$verb] = [
                'urlpath' => $urlpath,
                'uripath' => null,
                'suffix'  => null,
                'verb'  => $verb,
                'route' => $route,
                'alias' => $alias,
                'class' => $class,
                'method' => $method,
                'params' => [
                    'raw'  => $params,    // Route parameter keys from definition
                    'res'  => [],    // Route parameter values from request uri
                    'kv'   => [],    // Route parameters valideted as K-V format
                    'pipe' => [],    // Route parameters set by pipes
                ],
            ];
        }

        // Formatting doc data
        // Check docs compile enable status fisrt due to it's time wasting
        if (! ConfigManager::getEnv('PORT_DOCS_COMPILE', false)) {
            return;
        }

        $_version = self::formatDocVersion($_version);
        if ($nodoc) {
            return;
        }

        $docs = self::$docs[$_version]['main'][$domainKey] ?? [
            'title' => $domainTitle,
            'group' => [],
            'list'  => [],
        ];
        $params = [];
        $validators = [];
        foreach ($properties as $key => $arg) {
            $_doc = $arg['doc'] ?? [];
            $neednot = isset($_doc['NEED:0']) || ci_equal($_doc['NEED'] ?? '', 0);

            if ($neednot) {
                unset($_doc['NEED:0'], $_doc['NEED']);
            } else {
                $_doc['NEED'] = 1;
            }

            $param = [
                'name' => $key,
                'type' => $_doc['TYPE'] ?? null,
                'title' => $_doc['TITLE'] ?? null,
                'notes' => $_doc['NOTES'] ?? null,
                'default' => $_doc['DEFAULT'] ?? null,
                'location' => self::formatDocParameterLocation(
                    $mimein,
                    ($_doc['LOCATION'] ?? (array_key_exists('INROUTE', $_doc) ? 'route' : null))
                ),
                'compatibles' => $_doc['COMPATIBLE'] ?? [],
            ];

            array_unset(
                $_doc,
                '__ext__',
                'TITLE',
                'TYPE',
                'NOTES',
                'DEFAULT',
                'LOCATION',
                'COMPATIBLE'
            );

            if ($wrapin = ($_doc['WRAPIN'] ?? false)) {
                $_doc['WRAPIN'] = self::formatDocNamespace($wrapin);
            }
            $param['validators'] = $_doc;
            $params[] = $param;
        }

        if ($mimein) {
            $headerInList['CONTENT-TYPE'] = join('; ', [Request::getMimeByAlias($mimein, '?'), 'charset=UTF-8']);
        }
        if ($mimeout) {
            $headerOutList['CONTENT-TYPE'] = join('; ', [Request::getMimeByAlias($mimeout, '?'), 'charset=UTF-8']);
        }

        $doc = [
            'route' => $urlpath,
            'auth'  => $auth,
            'verbs' => $verbs,
            'class' => $class,
            'method' => $method,
            'title'  => $title,
            'remark' => $remark,
            'nodump' => $nodump,
            'model'  => $model,
            'author'  => $author,
            'status'  => $status,
            'version' => $_version,
            'params'  => $params,
            'suffixs' => $suffix,
            'fields'  => [
                'model' => self::formatDocModel($model),
                'compatibles' => ($assembler ? singleton($assembler)->compatibles() : []),
            ],
            'wrapout' => self::formatDocWrapout($wrapout),
            'wraperr' => self::formatDocWraperr($wraperr),
            'headers' => [
                'in'  => $headerInList,
                'out' => $headerOutList,
                'status' => $headerStatus,
            ],
            'subtitle' => $subtitle,
            'sortings' => self::formatSortings(
                $pipeinList[Sorting::class]['fields'] ?? null,
                $ofProperties
            ),
        ];

        $groups = self::formatDocGroups(array_merge($defaultGroup, $group), $class);

        if ($groups) {
            $docs['group'] = self::dynamicAppendDocWithGroups($docs['group'] ?? [], $doc, $groups);
        } else {
            $docs['list'][] = $doc;
        }

        self::$docs[$_version]['main'][$domainKey] = $docs;
        $appendixes = self::$docs[$_version]['appendixes'] ?? [];
        if (! ($appendixes['global'] ?? false)) {
            $appendixesGlobal = ConfigManager::getDomain('docs.appendixes', []);
            if ($appendixesGlobal) {
                $appendixes['global'] = self::formatDocAppendixes(
                    $appendixesGlobal,
                    '__global__',
                    '',
                    Kernel::getRoot()
                );
            }
        }
        $appendixesDomain = ConfigManager::getDomainDomainByNamespace($class, 'docs.appendixes', []);
        if ($appendixesDomain) {
            $appendixes['domain'][$domainKey] = self::formatDocAppendixes(
                $appendixesDomain,
                $domainKey,
                $domainTitle,
                DomainManager::getByNamespace($class)
            );
        }

        self::$docs[$_version]['appendixes'] = $appendixes;
    }

    public static function formatSortings(string $sortings = null, array $ofProperties = [])
    {
        if (! $sortings) {
            return null;
        }

        $sortings = array_trim_from_string($sortings, ',');

        $list = [];
        foreach ($sortings as $field) {
            $title = $ofProperties[$field]['doc']['TITLE'] ?? null;

            $list[$field] = $title;
        }

        return $list;
    }

    public static function formatPipes($pipes)
    {
        if (! is_array($pipes)) {
            return [];
        }

        $list = [];

        foreach ($pipes as $pipe => $ext) {
            if (is_int($pipe) && is_string($ext)) {
                $list[$ext] = [];
                continue;
            }
            if (is_string($pipe)) {
                $list[$pipe] = is_array($ext) ? $ext : [];
                continue;
            }
        }

        return $list;
    }

    public static function formatDocAppendixes(
        array $appendixes,
        string $domainKey,
        string $domainTitle,
        string $domainRoot
    ) : array {
        array_walk($appendixes, function (&$item) use ($domainKey, $domainTitle, $domainRoot) {
            $item['domain'] = $domainTitle;
            $path = $item['path'] ?? null;
            if (! $path) {
                return;
            }

            $item['key']  = $domainKey;
            $item['path'] = ospath($domainRoot, $path);
        });

        return $appendixes;
    }

    public static function formatDocParameterLocation(string $mimein = null, string $location = null) : array
    {
        if ($location) {
            if (ci_equal($location, 'route')) {
                return ['URL Path'];
            }

            return [$location];
        }

        return ['Request Body', 'Query String'];
    }

    public static function formatDocModel(string $model = null)
    {
        if (! $model) {
            return [];
        }
        $annotation = Annotation::getByNamespace($model);
        if (! $annotation) {
            return [];
        }
    
        list($class, $properties, ) = $annotation;

        $_properties = [];
        foreach ($properties as $name => list('doc' => $options)) {
            $_properties[] = [
                'name' => $name,
                'type' => $options['TYPE']  ?? null,
                'title' => $options['TITLE'] ?? null,
                'notes' => $options['NOTES'] ?? null,
                'arguments' => $options['__ext__']['ARGUMENT'] ?? [],
            ];
        }

        return [
            'title' => $class['doc']['TITLE'] ?? null,
            'properties' => $_properties,
        ];
    }

    public static function formatDocWraperr(string $wraperr = null)
    {
        if (! $wraperr) {
            return;
        }

        return self::formatDocWrapper(singleton($wraperr)->wraperr());
    }

    public static function formatDocWrapper(array $wrapper = null)
    {
        if (! $wrapper) {
            return;
        }

        try {
            if (is_array($wrapper)) {
                $res = [];
                foreach ($wrapper as $key => $val) {
                    $_key = is_int($key) ? $val : $key;
                    $_val = is_int($key) ? null : $val;
                    if (in_array($_key, [
                        '__DATA__',
                        '__INFO__',
                        '__PAGINATOR__',
                    ])) {
                        $_val = $_key;
                        $_key = $val;
                    }

                    $res[$_key] = $_val;
                }

                $res = get_buffer_string(function () use ($res) {
                    print_r($res);
                });

                $res = str_replace('[', '', $res);
                $res = str_replace(']', '', $res);
                $res = array_trim_from_string($res, PHP_EOL);
                array_unset($res, 0, 1, (count($res) -1));

                return join(PHP_EOL, $res);
            }
        } catch (Throwable $e) {
        }
    }

    public static function formatDocWrapout(string $wrapout = null)
    {
        if (! $wrapout) {
            return;
        }

        return self::formatDocWrapper(singleton($wrapout)->wrapout());
    }

    public static function formatDocVersion(string $version = null)
    {
        if ($version) {
            $version = trim($version);
        }
        if (! $version) {
            return 'vX';
        }

        return $version;
    }

    public static function formatDocNamespace(string $namespace = null)
    {
        if (! $namespace) {
            return null;
        }

        $arr = array_trim_from_string($namespace, '\\');
        unset($arr[0]);

        return join('.', $arr);
    }

    private static function formatDocGroups(array $groups, string $domain)
    {
        if (! $groups) {
            return [];
        }

        $res = [];
        foreach ($groups as $group) {
            $title = ConfigManager::getDomainDomainByNamespace($domain, 'docs.groups')[$group] ?? $group;
            $res[$group] = $title;
        }

        return $res;
    }

    /**
     * Append $append into $data dynamically with $groups
     */
    private static function dynamicAppendDocWithGroups(array $data, $append, array $groups)
    {
        $keys = $groups;
        foreach ($groups as $name => $title) {
            $data[$name]['title'] = $title;
            if (false === next($groups)) {
                $data[$name]['list'][] = $append;
                return $data;
            }

            $_data = $data[$name]['group'] ?? [];
            unset($keys[$name]);

            // Here $keys must be unempty coz we check next($groups) before
            $data[$name]['group'] = self::dynamicAppendDocWithGroups($_data, $append, $keys);
            $data[$name]['list']  = array_merge(($data[$name]['list'] ?? []), ($_data['list'] ?? []));
            return $data;
        }
    }

    /**
     * De-duplicate route and alias definitions
     */
    public static function deduplicate(
        string $route,
        string $verb,
        string $urlpath,
        string $class,
        string $method,
        string $alias = null,
        bool $exception = false
    ) {
        if ($exists = (self::$routes[$route][$verb] ?? false)) {
            if (! $exception) {
                return false;
            }

            exception('DuplicateRouteDefinition', [
                'verb'  => $verb,
                'route' => $route,
                'conflict' => [
                    'class'  => $exists['class']   ?? null,
                    'method' => $exists['method']  ?? null,
                    'path'   => $exists['urlpath'] ?? null,
                ],
                'current' => [
                    'class'  => $class,
                    'method' => $method,
                    'path'   => $urlpath,
                ],
            ]);
        }

        if ($alias && ($exists = (self::$aliases[$alias] ?? false))) {
            if (! $exception) {
                return false;
            }

            exception('DuplicateRouteAliasDefinition', [
                'alias' => $alias,
                'conflict' => [
                    'verb'   => $exists['verb']    ?? null,
                    'route'  => $exists['route']   ?? null,
                    'class'  => $exists['class']   ?? null,
                    'method' => $exists['method']  ?? null,
                    'path'   => $exists['urlpath'] ?? null,
                ],
                'previous' => [
                    'verb'   => $verb,
                    'route'  => $route,
                    'class'  => $class,
                    'method' => $method,
                    'path'   => $urlpath,
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

    public static function __annotationFilterArgument(string $arg, array $ext = [], string $namespace = null) : array
    {
        $params = [];

        foreach ($ext as $rule => $error) {
            $arr = array_trim_from_string($rule, ':');
            $_rule = $arr[0] ?? false;
            unset($arr[0]);
            $_ext = join('', $arr);
            if (! $_rule) {
                continue;
            }
            $_ext = ('' === $_ext) ? '' : ":{$_ext}";
            $params[strtoupper($_rule).$_ext] = $error;
        }

        $argvs = array_trim_from_string($arg, ',');
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

    public static function __annotationMultipleMergeArgument()
    {
        return true;
    }

    public static function __annotationFilterPipein(string $pipein, array $ext, string $namespace = null) : ?array
    {
        $pipein = trim($pipein);
        if (! $pipein) {
            return null;
        }
        if (class_exists($pipein)) {
            return [formatns($pipein) => $ext];
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingPipeInUseClass', compact('pipein', 'namespace'));
        }

        $_pipein = get_annotation_ns($pipein, $namespace);
        if ((! $_pipein) || (! class_exists($_pipein))) {
            exception('PipeInNotExists', compact('pipein', 'namespace'));
        }

        return [$_pipein => $ext];
    }

    public static function __annotationFilterNopipein(string $nopipein, array $ext, string $namespace = null) : ?array
    {
        $nopipein = trim($nopipein);
        if (! $nopipein) {
            return null;
        }
        if (class_exists($nopipein)) {
            return [formatns($nopipein) => $ext];
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingNoPipeInUseClass', compact('nopipein', 'namespace'));
        }

        $_nopipein = get_annotation_ns($nopipein, $namespace);
        if ((! $_nopipein) || (! class_exists($_nopipein))) {
            exception('NoPipeInNotExists', compact('nopipein', 'namespace'));
        }

        return [$_nopipein => $ext];
    }

    public static function __annotationFilterNopipeout(string $nopipeout, array $ext, string $namespace = null) : ?array
    {
        $nopipeout = trim($nopipeout);
        if (! $nopipeout) {
            return null;
        }
        if (class_exists($nopipeout)) {
            return [formatns($nopipeout) => $ext];
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingNoPipeOutUseClass', compact('nopipeout', 'namespace'));
        }

        $_nopipeout = get_annotation_ns($nopipeout, $namespace);
        if ((! $_nopipeout) || (! class_exists($_nopipeout))) {
            exception('NoPipeOutNotExists', compact('nopipeout', 'namespace'));
        }

        return [$_nopipeout => $ext];
    }

    public static function __annotationFilterPipeout(string $pipeout, array $ext, string $namespace = null) : ?array
    {
        $pipeout = trim($pipeout);
        if (! $pipeout) {
            return null;
        }
        if (class_exists($pipeout)) {
            return [formatns($pipeout) => $ext];
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingPipeOutUseClass', compact('pipeout', 'namespace'));
        }

        $_pipeout = get_annotation_ns($pipeout, $namespace);
        if ((! $_pipeout) || (! class_exists($_pipeout))) {
            exception('PipeOutNotExists', compact('pipeout', 'namespace'));
        }

        return [$_pipeout => $ext];
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

    public static function __annotationFilterHeaderout(string $headersOut, array $ext, string $namespace) : array
    {
        $val = $ext['notes'] ?? (array_keys($ext)[0] ?? null);
        $res = [];
        $headers = array_trim_from_string(trim($headersOut), ',');
        foreach ($headers as $header) {
            $res[strtoupper($header)] = $val;
        }

        return $res;
    }

    public static function __annotationMultipleHeaderout() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeHeaderout()
    {
        return true;
    }

    public static function __annotationFilterHeaderin(string $headersIn, array $ext, string $namespace) : array
    {
        $val = $ext['notes'] ?? (array_keys($ext)[0] ?? null);
        $res = [];
        $headers = array_trim_from_string(trim($headersIn), ',');
        foreach ($headers as $header) {
            $res[strtoupper($header)] = $val;
        }

        return $res;
    }

    public static function __annotationMultipleMergeHeaderin()
    {
        return true;
    }

    public static function __annotationMultipleHeaderin() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeHeaderStatus()
    {
        return 'assoc';
    }

    public static function __annotationFilterHeaderstatus(string $headerStatus, array $ext, string $namespace) : array
    {
        $val = $ext['notes'] ?? (array_keys($ext)[0] ?? null);

        return [trim($headerStatus) => $val];
    }

    public static function __annotationMultipleHeaderstatus() : bool
    {
        return true;
    }

    public static function __annotationFilterStatus(string $status, array $ext, string $namespace) : string
    {
        $status = trim($status);

        if (mb_strlen($status) === 0) {
            return '1';
        }

        $notes = $ext['notes'] ?? (array_keys($ext)[0] ?? null);
        if (! $notes) {
            return $status;
        }

        $notes = $notes ? "({$notes})" : '';

        return "{$status} {$notes}";
    }

    public static function __annotationFilterAuth(string $auth, array $ext, string $namespace) : string
    {
        $auth = trim($auth);
        if (! in_array($auth, self::AUTH_TYPES)) {
            exception('BadAuthType', compact('namespace', 'auth'));
        }

        $notes = $ext['notes'] ?? (array_keys($ext)[0] ?? null);
        if (! $notes) {
            return $auth;
        }

        $notes = $notes ? "({$notes})" : '';

        return "{$auth} {$notes}";
    }

    public static function __annotationFilterVerb(string $verbs, array $ext, string $namespace) : array
    {
        $verbs = array_trim_from_string($verbs, ',');
        foreach ($verbs as &$_verb) {
            $verb  = $_verb;
            $_verb = strtoupper($_verb);
            if (! in_array($_verb, self::HTTP_VERBS)) {
                exception('InvalidHttpVerb', compact('verb', 'namespace'));
            }
        }

        return $verbs;
    }

    public static function __annotationFilterAssembler(string $assembler, array $ext = [], string $namespace = null)
    {
        $assembler = trim($assembler);
        if (! $assembler) {
            return null;
        }
        if ($assembler === '_') {
            return $assembler;
        }
        if (class_exists($assembler)) {
            return $assembler;
        }
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('MissingAssemblerUseClass', compact('assembler', 'namespace'));
        }

        $_assembler = get_annotation_ns($assembler, $namespace);
        if ((! $_assembler) || (! class_exists($_assembler))) {
            exception('AssemblerNotExists', compact('assembler', 'namespace'));
        }

        return $_assembler;
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

    public static function __annotationFilterGroup(string $group, array $ext = [], string $namespace = null)
    {
        return array_trim_from_string($group, '/');
    }

    public static function __annotationFilterVersion(string $version, array $ext = [], string $namespace = null)
    {
        return [$version, array_change_key_case($ext, CASE_UPPER)];
    }

    public static function __annotationFilterLogging(string $logging, array $ext = [], string $namespace = null)
    {
        $logging = trim($logging);

        if (! interface_exists($logging)) {
            exception('LoggingRepositoryNotExists', compact('namespace', 'logging'));
        }
        if (! is_subclass_of($logging, Repository::class)) {
            exception('LoggingNotSubClassOfRepository', compact('namespace', 'logging'));
        }
        if (! method_exists($logging, 'logging')) {
            exception('MissingLoggingMethodInRepository', compact('namespace', 'logging'));
        }

        return $logging;
    }

    public static function __annotationFilterRoute(string $val, array $ext = [], string $namespace = null)
    {
        $arr = array_trim(explode('/', trim($val)));

        return empty($arr) ? '/' : join('/', $arr);
    }

    public static function __annotationMultipleVerb() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeVerb()
    {
        return 'index';
    }

    public static function __annotationMultiplePipeout() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergePipeout()
    {
        return true;
    }

    public static function __annotationMultiplePipein() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergePipein()
    {
        return true;
    }

    public static function __annotationMultipleNopipein() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeNopipein()
    {
        return true;
    }

    public static function __annotationMultipleNopipeout() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeNopipeout()
    {
        return true;
    }

    public static function __annotationMultipleLogmaskkey() : bool
    {
        return true;
    }

    public static function __annotationMultipleMergeLogmaskkey()
    {
        return true;
    }

    /**
     * Get port definitions by route definition
     *
     * @param array $route: $route item
     */
    public static function get(array $route) : ?array
    {
        $class  = $route['class']  ?? null;
        $method = $route['method'] ?? null;

        return self::$ports[$class][$method] ?? null;
    }

    public static function getAliases() : array
    {
        return self::$aliases;
    }

    public static function getPorts() : array
    {
        return self::$ports;
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
