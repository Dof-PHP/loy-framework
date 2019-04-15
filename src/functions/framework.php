<?php

declare(strict_types=1);

if (! function_exists('collect')) {
    function collect(array $data, $origin = null, bool $recursive = true)
    {
        if (! $recursive) {
            return \Loy\Framework\Facade\Collection::new($data, $origin, false);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = collect($value, null, true);
            }
        }

        return \Loy\Framework\Facade\Collection::new($data, $origin, $recursive);
    }
}
if (! function_exists('uncollect')) {
    function uncollect($value)
    {
        $data = $value;
        if ($value instanceof \Loy\Framework\Collection) {
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
                return \Loy\Framework\DomainManager::collectByFile($file);
            }
        }
        if (\Loy\Framework\DomainManager::hasKey($key)) {
            return \Loy\Framework\DomainManager::collectByKey($key);
        }
        if (class_exists($key)) {
            return \Loy\Framework\DomainManager::collectByNamespace($key);
        }
        if (is_file($key)) {
            return \Loy\Framework\DomainManager::collectByFile($key);
        }
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
            $object = \Loy\Framework\Container::di($service);
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
        return \Loy\Framework\Facade\Annotation::get($target, $origin, $file, $cache);
    }
}
if (! function_exists('config')) {
    function config(string $key = 'domain')
    {
        return \Loy\Framework\ConfigManager::get($key);
    }
}
if (! function_exists('validate')) {
    function validate(array $data, array $rules = [])
    {
        return \Loy\Framework\Facade\Validator::setData($data)->setRules($rules)->execute();
    }
}
if (! function_exists('request')) {
    /**
     * Get the request instance related to current http session
     */
    function request()
    {
        return \Loy\Framework\Facade\Request::getInstance();
    }
}
if (! function_exists('response')) {
    /**
     * Get the response instance related to current http session
     */
    function response()
    {
        return \Loy\Framework\Facade\Response::getInstance();
    }
}
if (! function_exists('route')) {
    /**
     * Get the route collection instance related to current request
     */
    function route()
    {
        return \Loy\Framework\Web\Route::getInstance();
    }
}
if (! function_exists('get_annotation_ns')) {
    function get_annotation_ns($annotation, string $origin)
    {
        if (! is_string($annotation)) {
            $type = gettype($annotation);
            exception('NonStringAnnotatinForNamespace', compact('annotation', 'type'));
        }
        if (class_exists($annotation)) {
            return $annotation;
        }

        if ('::class' === mb_substr($annotation, -7, 7)) {
            $annotation = mb_substr($annotation, 0, -7);
        }

        if (class_exists($annotation)) {
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
        if (class_exists($samens)) {
            return $samens;
        }

        return false;
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
            $handler = \Loy\Framework\Web\Kernel::WRAPOUT_HANDLER;
        } elseif (ci_equal($type, 'in')) {
            $handler = \Loy\Framework\Web\Kernel::WRAPIN_HANDLER;
        } elseif (ci_equal($type, 'err')) {
            $handler = \Loy\Framework\Web\Kernel::WRAPERR_HANDLER;
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
        return \Loy\Framework\Facade\Curl::init($url, $params, $headers, $options);
    }
}
if (! function_exists('http_head')) {
    function http_head(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::head($url, $params, $headers, $options);
    }
}
if (! function_exists('http_get')) {
    function http_get(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::get($url, $params, $headers, $options);
    }
}
if (! function_exists('http_post')) {
    function http_post(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::post($url, $params, $headers, $options);
    }
}
if (! function_exists('http_put')) {
    function http_put(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::put($url, $params, $headers, $options);
    }
}
if (! function_exists('http_patch')) {
    function http_patch(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::patch($url, $params, $headers, $options);
    }
}
if (! function_exists('http_delete')) {
    function http_delete(string $url, $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::delete($url, $params, $headers, $options);
    }
}
if (! function_exists('singleton')) {
    function singleton(string $namespace, ...$params)
    {
        return \Loy\Framework\Facade\Singleton::get($namespace, ...$params);
    }
}
if (! function_exists('is_collection')) {
    function is_collection($var)
    {
        return is_object($var) && ($var instanceof \Loy\Framework\Collection);
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
            return ci_equal($e->getMessage(), $name);
        }

        return ci_equal(objectname($e), $name);
    }
}
if (! function_exists('paginator')) {
    function paginator(array $list, array $params = [])
    {
        return new \Loy\Framework\Paginator($list, $params);
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
            $success = \Loy\Framework\GWT::execute($given, $when, $then, $result);
        } catch (Throwable $e) {
            $success = null;
            $trace = $e->getTrace()[0] ?? [];

            $result = [
                $trace['file'],
                $trace['line'],
                $trace['args'],
                '__TESING_EXCEPTION__' => true,
            ];
        }

        \Loy\Framework\GWT::append($title, $file, $line, $result, $success);
    }
}
if (! function_exists('run_gwt_tests')) {
    function run_gwt_tests(string $dir, array $excludes = [])
    {
        \Loy\Framework\GWT::run($dir, $excludes);
    }
}
