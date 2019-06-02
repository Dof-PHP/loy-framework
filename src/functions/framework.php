<?php

declare(strict_types=1);

if (! function_exists('pathof')) {
    /**
     * Get path in dof project
     */
    function pathof(...$params)
    {
        return ospath(\Dof\Framework\Kernel::getRoot(), $params);
    }
}
if (! function_exists('get_dof_version_raw')) {
    function get_dof_version_raw() : int
    {
        $path = ospath(dirname(dirname(dirname(__FILE__))), '.VER.DOF');
        if (! is_file($path)) {
            return 0;
        }

        return intval(file_get_contents($path));
    }
}
if (! function_exists('get_dof_version')) {
    // --------------------------------------------
    //     The version format used in Dof:
    //     [major].[minor].[release].[build]
    //     2 commits = 1 build
    //     1 release = 16 build  = 32 commits
    //     1 minor   = 8 release = 256 commits
    //     1 major   = 4 minor   = 1024 commits
    // --------------------------------------------
    function get_dof_version()
    {
        $raw = get_dof_version_raw();
        if (0 === $raw) {
            return '0.0.0.0';
        }

        $ver = $left = $raw;
        $major   = floor($left / 1024);
        $left    = $ver - $major*1024;
        $minor   = floor($left / 256);
        $left    = $left - $minor*256;
        $release = floor($left / 32);
        $left    = $left - $release*32;
        $build   = floor($left / 2);

        return $major.'.'.$minor.'.'.$release.'.'.$build;
    }
}
if (! function_exists('collect')) {
    function collect(array $data, $origin = null, bool $recursive = true)
    {
        if (! $recursive) {
            return (($data === []) || is_assoc_array($data))
                ? \Dof\Framework\Facade\Collection::new($data, $origin, false)
                : $data;
        }

        foreach ($data as $key => &$value) {
            if (is_array($value) && (($value === []) || is_assoc_array($value))) {
                $value = collect($value, null, true);
            }
        }

        return (($data === []) || is_assoc_array($data))
            ? \Dof\Framework\Facade\Collection::new($data, $origin, $recursive)
            : $data;
    }
}
if (! function_exists('uncollect')) {
    function uncollect($value)
    {
        $data = $value;
        if ($value instanceof \Dof\Framework\Collection) {
            $data = $value->toArray();
        } elseif (is_array($value)) {
        } else {
            return $value;
        }
        $result = [];
        foreach ($data as $key => $item) {
            if (is_collection($item)) {
                $result[$key] = uncollect($item);
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
if (! function_exists('domain')) {
    /**
     * Get a domain instance by given key
     *
     * @param string $key
     */
    function domain(string $key = null)
    {
        if (is_null($key)) {
            try {
                throw new \Exception;
            } catch (\Exception $e) {
                $file = $e->getTrace()[0]['file'] ?? null;
                return \Dof\Framework\DomainManager::collectByFile($file);
            }
        }
        if (\Dof\Framework\DomainManager::hasKey($key)) {
            return \Dof\Framework\DomainManager::collectByKey($key);
        }
        if (class_exists($key)) {
            return \Dof\Framework\DomainManager::collectByNamespace($key);
        }
        if (is_file($key)) {
            return \Dof\Framework\DomainManager::collectByFile($key);
        }
    }
}
if (! function_exists('config')) {
    function config(string $type, string $key)
    {
        return \Dof\Framework\ConfigManager::get(join('.', [$type, $key]));
    }
}
if (! function_exists('service')) {
    function service($service, array $params = [])
    {
        $object = $service;
        if (! is_object($service)) {
            if (! class_exists($service)) {
                exception('ServiceNotExists', ['service' => string_literal($service)]);
            }
            $object = \Dof\Framework\Container::di($service);
        }

        foreach ($params as $key => $val) {
            $setter = 'set'.ucfirst((string) $key);
            if (method_exists($service, $setter)) {
                $object->{$setter}($val);
            }
        }

        return $object;
    }
}
if (! function_exists('assemble')) {
    function assemble($target, $assembler)
    {
        // TODO
    }
}
if (! function_exists('annotation')) {
    function annotation(string $target, string $origin = null, bool $file = false, bool $cache = true) : array
    {
        return \Dof\Framework\Facade\Annotation::get($target, $origin, $file, $cache);
    }
}
if (! function_exists('validate')) {
    function validate(array $data, array $rules = [])
    {
        return \Dof\Framework\Facade\Validator::setData($data)->setRules($rules)->execute();
    }
}
if (! function_exists('request')) {
    /**
     * Get the request instance related to current http session
     */
    function request()
    {
        return \Dof\Framework\Facade\Request::getInstance();
    }
}
if (! function_exists('response')) {
    /**
     * Get the response instance related to current http session
     */
    function response()
    {
        return \Dof\Framework\Facade\Response::getInstance();
    }
}
if (! function_exists('port')) {
    /**
     * Get the port collection instance related to current request
     */
    function port(string $annotation = null)
    {
        $port = \Dof\Framework\Web\Port::getInstance();

        return $annotation ? $port->{$annotation} : $port;
    }
}
if (! function_exists('pargvs')) {
    /**
     * Get the port arguments array
     */
    function pargvs(string $annotation = null)
    {
        return \Dof\Framework\Web\Port::argvs();
    }
}
if (! function_exists('route')) {
    /**
     * Get the route collection instance related to current request
     */
    function route(string $annotation = null)
    {
        $route = \Dof\Framework\Web\Route::getInstance();

        return $annotation ? $route->{$annotation} : $route;
    }
}
if (! function_exists('get_annotation_ns')) {
    function get_annotation_ns($annotation, string $origin)
    {
        if (! is_string($annotation)) {
            $type = gettype($annotation);
            exception('NonStringAnnotatinForNamespace', compact('annotation', 'type'));
        }
        if (namespace_exists($annotation)) {
            return $annotation;
        }

        if ('::class' === mb_substr($annotation, -7, 7)) {
            $annotation = mb_substr($annotation, 0, -7);
        }

        if (namespace_exists($annotation)) {
            return $annotation;
        }

        $uses = get_used_classes($origin);
        if ($uses) {
            foreach ($uses as $ns => $alias) {
                if ($alias === $annotation) {
                    return $ns;
                }
                $_ns   = explode('\\', $ns);
                $short = $_ns[count($_ns) - 1];
                if ($short === $annotation) {
                    return $ns;
                }
            }
        }

        // Last try from same namespace of origin
        $_origin = array_trim_from_string($origin, '\\');
        $_origin[count($_origin) - 1] = $annotation;
        $samens  = join('\\', $_origin);
        if (namespace_exists($samens)) {
            return $samens;
        }

        return false;
    }
}
if (! function_exists('logging')) {
    function logging(string $level, string $message, ...$context)
    {
        return call_user_func_array([\Dof\Framework\Facade\Log::class, $level], [$message, $context]);
    }
}
if (! function_exists('logger')) {
    function logger()
    {
        return \Dof\Framework\Facade\Log::getInstance();
    }
}
if (! function_exists('wrapper')) {
    function wrapper(string $namespace = null, string $type = null)
    {
        if (is_null($namespace)) {
            return null;
        }
        if (! class_exists($namespace)) {
            exception('WrapperClassNotExists', compact('namespace'));
        }

        if (ci_equal($type, 'out')) {
            $handler = \Dof\Framework\Web\Kernel::WRAPOUT_HANDLER;
        } elseif (ci_equal($type, 'in')) {
            $handler = \Dof\Framework\Web\Kernel::WRAPIN_HANDLER;
        } elseif (ci_equal($type, 'err')) {
            $handler = \Dof\Framework\Web\Kernel::WRAPERR_HANDLER;
        } else {
            return null;
        }

        if (! method_exists($namespace, $handler)) {
            exception('WrapperHandlerNotExists', [
                'namespace' => $namespace,
                'handler'   => $handler,
            ]);
        }

        return singleton($namespace)->{$handler}();
    }
}
if (! function_exists('rpc')) {
    function rpc()
    {
        // TODO
    }
}
if (! function_exists('http')) {
    function http(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Dof\Framework\Facade\Curl::init($url, $params, $headers, $options);
    }
}
if (! function_exists('http_head')) {
    function http_head(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Dof\Framework\Facade\Curl::head($url, $params, $headers, $options);
    }
}
if (! function_exists('http_get')) {
    function http_get(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Dof\Framework\Facade\Curl::get($url, $params, $headers, $options);
    }
}
if (! function_exists('http_post')) {
    function http_post(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Dof\Framework\Facade\Curl::post($url, $params, $headers, $options);
    }
}
if (! function_exists('http_put')) {
    function http_put(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Dof\Framework\Facade\Curl::put($url, $params, $headers, $options);
    }
}
if (! function_exists('http_patch')) {
    function http_patch(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Dof\Framework\Facade\Curl::patch($url, $params, $headers, $options);
    }
}
if (! function_exists('http_delete')) {
    function http_delete(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Dof\Framework\Facade\Curl::delete($url, $params, $headers, $options);
    }
}
if (! function_exists('singleton')) {
    function singleton(string $namespace, ...$params)
    {
        return \Dof\Framework\Facade\Singleton::get($namespace, ...$params);
    }
}
if (! function_exists('is_collection')) {
    function is_collection($var)
    {
        return is_object($var) && ($var instanceof \Dof\Framework\Collection);
    }
}
if (! function_exists('is_exception')) {
    function is_exception($e, string $name = null) : bool
    {
        if (! ($e instanceof \Throwable)) {
            return false;
        }
        if (is_null($name)) {
            return true;
        }

        if (is_anonymous($e)) {
            $_name = method_exists($e, 'getName') ? $e->getName() : $e->getMessage();

            return ci_equal($_name, $name);
        }

        return ci_equal(objectname($e), $name);
    }
}
if (! function_exists('paginator')) {
    function paginator(array $list, array $params = [])
    {
        return new \Dof\Framework\Paginator($list, $params);
    }
}
if (! function_exists('GWT')) {
    function GWT(string $title, $given, $when, $then)
    {
        $last = debug_backtrace()[0] ?? [];
        $file = $last['file'] ?? 'unknown';
        $line = $last['line'] ?? 'unknown';

        $success = false;
        $result  = null;
        try {
            $success = \Dof\Framework\GWT::execute($given, $when, $then, $result);
        } catch (Throwable $e) {
            $success = null;
            $trace = $e->getTrace()[0] ?? [];

            $result = [
                $e->getMessage(),
                $trace['file'],
                $trace['line'],
                $trace['args'],
                '__TESING_EXCEPTION__' => true,
            ];
        }

        \Dof\Framework\GWT::append($title, $file, $line, $result, $success);
    }
}
if (! function_exists('run_gwt_tests')) {
    function run_gwt_tests(string $dir, array $excludes = [])
    {
        \Dof\Framework\GWT::run($dir, $excludes);
    }
}
if (! function_exists('array_get')) {
    function array_get($data, string $key = null, $default = null, array $rules = null)
    {
        if (! $key) {
            return null;
        }
        $val = ($data[$key] ?? null) ?? $default;
        if (! $rules) {
            return $val;
        }

        $validator = validate([$key => $val], [$key => $rules]);
        if (($fails = $validator->getFails()) && ($fail = $fails->first())) {
            $context = (array) $fail->value;

            exception($fail->key, $context);
        }

        return $validator->getResult()[$key] ?? null;
    }
}
