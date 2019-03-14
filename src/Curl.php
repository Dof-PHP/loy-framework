<?php

declare(strict_types=1);

namespace Loy\Framework;

final class Curl
{
    private $url = null;
    private $ch  = null;
    private $notInCB   = false;
    private $urlencode = true;
    private $sendAsJson = false;
    private $sendAsXml  = false;
    private $method  = null;
    private $params  = null;
    private $headers = [];
    private $options = [
        'default' => [],
        'custom'  => [],
    ];
    private $return  = [
        'status'  => 200,
        'message' => '',
        'headers' => [],
        'body'    => '',
    ];

    public function __construct(string $url = '', $params = [], array $headers = [], array $options = [])
    {
        $this->init($url, $params, $headers);
    }

    public function init(string $url = null, $params = null, array $headers = null, array $options = null)
    {
        if (! is_null($url)) {
            $this->setUrl($url);
        }
        if (! is_null($params)) {
            $this->setParams($params);
        }
        if (! is_null($headers)) {
            $this->setHeaders($headers);
        }
        if (! is_null($options)) {
            $this->setOptions($options);
        }

        return $this;
    }

    public function setOptions(array $options)
    {
        $this->options['custom'] = $options;

        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    public function setParams($params)
    {
        $this->params = $params;

        return $this;
    }

    public function sendAsXml(bool $asXml)
    {
        $this->sendAsXml  = $asXml;
        if ($asXml) {
            $this->sendAsJson = false;
        }

        return $this;
    }

    public function sendAsJson(bool $asJson)
    {
        $this->sendAsJson = $asJson;
        if ($asJson) {
            $this->sendAsXml = false;
        }

        return $this;
    }

    public function setUrlencode(bool $encode)
    {
        $this->urlencode = $encode;

        return $this;
    }

    public function setMethod(string $method)
    {
        $this->method = strtolower($method);

        return $this;
    }

    public function setUrl(string $url)
    {
        $this->url = $url;

        return $this;
    }

    public function post(string $url = null, $params = null, array $headers = null, array $options = null)
    {
        return $this->setMethod('post')->request([
            CURLOPT_POST   => true,
            CURLOPT_HEADER => false,
        ], $url, $params, $headers, $options);
    }

    public function get(string $url = null, $params = null, array $headers = null, array $options = null)
    {
        return $this->setMethod('get')->request([
            CURLOPT_HEADER => false,
        ], $url, $params, $headers, $options);
    }

    public function delete(string $url = null, $params = null, array $headers = null, array $options = null)
    {
        return $this->setMethod('delete')->request([
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HEADER         => false,
        ], $url, $params, $headers, $options);
    }

    public function patch(string $url = null, array $params = null, array $headers = null, array $options = null)
    {
        return $this->setMethod('patch')->request([
            CURLOPT_CUSTOMREQUEST  => 'PATCH',
            CURLOPT_HEADER         => false,
        ], $url, $params, $headers, $options);
    }

    public function put(string $url = null, $params = null, array $headers = null, array $options = null)
    {
        return $this->setMethod('put')->request([
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_HEADER     => false,
        ], $url, $params, $headers, $options);
    }

    public function head(string $url = null, $params = null, array $headers = null, array $options = null)
    {
        return $this->setMethod('head')->request([
            CURLOPT_CUSTOMREQUEST  => 'HEAD',
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
        ], $url, $params, $headers, $options);
    }

    public function request(array $default = [], string $url = null, $params = null, array $headers = null, array $options = null)
    {
        $this->init($url, $params, $headers, $options);

        if ($default) {
            $this->options['default'] = $default;
        }

        return $this->execute();
    }

    private function prepare()
    {
        if ($this->params) {
            if ($this->method === 'get') {
                $query  = parse_url($this->url, PHP_URL_QUERY);
                $params = is_array($this->params) ? http_build_query($this->params) : $this->params;
                $params = $query ? '&'.$params : '?'.$params;
                $params = $this->urlencode ? urlencode($params) : $params;
                $this->url .= $params;
            } else {
                if (is_array($this->params)) {
                    $this->params = $this->sendAsJson ? enjson($this->params) : (
                        $this->sendAsXml ? enxml($this->params) : (
                            $this->urlencode
                            ? urlencode(http_build_query($this->params))
                            : http_build_query($this->params)
                        )
                    );
                } elseif (is_scalar($this->params)) {
                    $this->params = (string) $this->params;
                }
            }
        }

        if ($this->sendAsJson) {
            $this->headers['Content-Type'] = 'application/json';
        }
        if ($this->sendAsXml) {
            $this->headers['Content-Type'] = 'application/xml';
        }
    }

    private function execute()
    {
        $this->prepare();

        $this->ch = curl_init($this->url);
        if (! $this->ch) {
            return $this->response(500, 'Request Init Failed');
        }

        if ($this->headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->encodeHeadersKV($this->headers));
        }
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, [$this, 'responseHeaderHandler']);

        if ($ua = ($_SERVER['HTTP_USER_AGENT'] ?? false)) {
            curl_setopt($this->ch, CURLOPT_USERAGENT, $ua);
        }

        if ($optionsDefault = ($this->options['default'] ?? [])) {
            if (! curl_setopt_array($this->ch, $optionsDefault)) {
                return $this->response(500, 'Request Options Setting Failed (default)');
            }
        }
        if ($optionsCustom = ($this->options['custom'] ?? [])) {
            if (! curl_setopt_array($this->ch, $optionsCustom)) {
                return $this->response(500, 'Request Options Setting Failed (custom)');
            }
        }

        if ($this->params && (in_array($this->method, ['post', 'put', 'patch', 'delete']))) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->params);
        }

        $result = curl_exec($this->ch);
        $errno  = curl_errno($this->ch);
        $error  = curl_error($this->ch);
        if ($errno > 0) {
            return $this->response(400, "CURL ERROR: {$error} ({$errno})");
        }
        $status = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->close();

        if ('head' === $this->method) {
            $result = null;
        }

        return $this->response($status, $result);
    }

    private function response(int $status, string $body = null)
    {
        return new class($status, $body, $this->return['headers'] ?? [], $this->return['message'] ?? '?') {
            public function __construct(
                int $status = 200,
                string $body = null,
                array $headers = [],
                string $message = ''
            ) {
                $this->status  = $status;
                $this->message = $message;
                $this->headers = $headers;
                $this->body    = $body;
            }
            public function isSuccess(int $success = null) : bool
            {
                if ($success) {
                    return $this->status === $success;
                }
                return (100 <= $this->status) && ($this->status < 400);
            }
            public function bodyAsXml()
            {
                return dexml($this->body);
            }
            public function bodyAsJson(bool $assoc = true)
            {
                return dejson($this->body, $assoc);
            }
        };
    }

    public function close()
    {
        if ($this->ch && $this->notInCB) {
            curl_close($this->ch);
        }

        $this->ch = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function responseHeaderHandler($ch, string $header)
    {
        $len = mb_strlen($header);
        $arr = explode(':', trim($header), 2);
        $cnt = count($arr);
        if ($cnt > 1) {
            $key = trim($arr[0] ?? '');
            $val = trim($arr[1] ?? '');
            $this->return['headers'][$key] = $val;
        } elseif ($cnt === 1) {
            $message = $arr[0] ?? '?';
            if ($message && (! ($this->return['message'] ?? false))) {
                $this->return['message'] = $message;
            }
        } elseif ($cnt === 0) {
            $this->notInCB = true;
        }

        return $len;
    }

    public function encodeHeadersKV(array $headers) : array
    {
        $res = [];
        foreach ($headers as $key => $value) {
            $value = is_array() ? join(';', $value) : $value;
            $res[] = "{$key}: {$value}";
        }
        return $res;
    }
}
