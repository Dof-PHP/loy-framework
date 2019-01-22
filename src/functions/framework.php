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

        return new \Loy\Framework\Base\Collection($data, $origin);
    }
}
if (! function_exists('validate')) {
    function validate(array $data, array $rule = [], array $message = [])
    {
        return \Loy\Framework\Base\Validator::check($data, $rule, $message);
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
if (! function_exists('http_head')) {
    function http_head(string $url, array $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::head($url, $params, $headers, $options);
    }
}
if (! function_exists('http_get')) {
    function http_get(string $url, array $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::get($url, $params, $headers, $options);
    }
}
if (! function_exists('http_post')) {
    function http_post(string $url, array $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::post($url, $params, $headers, $options);
    }
}
if (! function_exists('http_put')) {
    function http_put(string $url, array $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::put($url, $params, $headers, $options);
    }
}
if (! function_exists('http_patch')) {
    function http_patch(string $url, array $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::patch($url, $params, $headers, $options);
    }
}
if (! function_exists('http_delete')) {
    function http_delete(string $url, array $params = [], array $headers = [], array $options = [])
    {
        return \Loy\Framework\Facade\Curl::delete($url, $params, $headers, $options);
    }
}
