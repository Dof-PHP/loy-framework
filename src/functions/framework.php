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
    function domain()
    {
        try {
            throw new \Exception;
        } catch (\Exception $e) {
            $filepath = $e->getTrace()[0]['file'] ?? null;
            return \Loy\Framework\Base\DomainManager::initFromFilepath($filepath);
        }
    }
}
if (! function_exists('validate')) {
    function validate(array $data, array $rule = [], array $message = [], bool $exception = false)
    {
        try {
            return \Loy\Framework\Base\Validator::execute($data, $rule, $message);
        } catch (\Loy\Framework\Base\Exception\ValidationFailureException $e) {
            if ($exception) {
                throw $e;
            }
            return $e->getMessage();
        }
    }
}
if (! function_exists('request')) {
    function request()
    {
        return \Loy\Framework\Web\Request::getInstance();
    }
}
if (! function_exists('response')) {
    function response()
    {
        return \Loy\Framework\Web\Response::getInstance();
    }
}
if (! function_exists('port_get')) {
    function port_get(string $uri, array $params = [], array $headers = [])
    {
        return \Loy\Framework\Web\Request::make('get', $uri, $params, $headers);
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
