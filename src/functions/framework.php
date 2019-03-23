<?php

declare(strict_types=1);

if (! function_exists('collect')) {
    function collect(array $data, $origin = null)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = collect($value, $origin);
            }
        }

        return \Loy\Framework\Facade\Collection::new($data, $origin);
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
    function service($service, array $params = [], bool $execute = true)
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

        return $execute ? $object->execute() : $object;
    }
}
if (! function_exists('assemble')) {
    function assemble($target, $assembler)
    {
        // TODO
    }
}
if (! function_exists('annotation')) {
    function annotation(string $target, bool $file = false) : array
    {
        return \Loy\Framework\Facade\Annotation::get($target, $file);
    }
}
if (! function_exists('config')) {
    function config(string $key = 'domain')
    {
        return \Loy\Framework\ConfigManager::get($key);
    }
}
if (! function_exists('validate')) {
    function validate(array $data, array $rule = [], array $message = [])
    {
        return \Loy\Framework\Validator::execute($data, $rule, $message);
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
