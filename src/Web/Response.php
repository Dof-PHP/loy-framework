<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

class Response
{
    use HttpTrait;

    /** @var bool: user defined response error status */
    private $error  = null;

    /** @var int: HTTP response status code */
    private $status = 200;

    /** @var string: HTTP response status message */
    private $info  = 'ok';

    /** @var string: HTTP response body content */
    private $body  = '';

    /** @var string|enum: HTTP response content-type short name */
    private $mime  = 'text/html';

    /** @var array: Response body structure elements (KV) */
    private $wrappers = [
        'out' => [],
        'err' => [],
    ];

    public function text($body, int $status = 200, array $headers = [])
    {
        $this->body    = (string) $body;
        $this->status  = $status;
        $this->headers = $headers;
        $this->mime    = 'text/plain';

        return $this;
    }

    public function view(string $body, int $status = 200, array $headers = [])
    {
        return $this->html($body, $status, $headers);
    }

    public function html(string $body, int $status = 200, array $headers = [])
    {
        $this->body    = $body;
        $this->status  = $status;
        $this->headers = $headers;
        $this->mime    = 'text/html';

        return $this;
    }

    public function xml($body, int $status = 200, array $headers = [])
    {
        $this->body    = enxml($body);
        $this->status  = $status;
        $this->headers = $headers;
        $this->mime    = 'application/xml';

        return $this;
    }

    public function json($body, int $status = 200, array $headers = [])
    {
        $this->body    = enjson($body);
        $this->status  = $status;
        $this->headers = $headers;
        $this->mime    = 'application/json';

        return $this;
    }

    public function send($body = null, int $status = null, array $headers = null)
    {
        if ($body instanceof Response) {
            return $body->__send();
        }

        if (! is_null($body)) {
            $this->stringBody($body);
        }
        if (! is_null($status)) {
            $this->status = $status;
        }
        if (! is_null($headers)) {
            $this->headers = $headers;
        }

        $this->__send();
    }

    private function __send()
    {
        if ($this->headers) {
            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        if ($this->mime) {
            header("Content-Type: {$this->mime}; charset=UTF-8");
        }

        http_response_code($this->status);
        echo stringify($this->body);
        exit(0);
    }

    private function stringBody($body)
    {
        $alias = array_search($this->mime, self::$mimes);
        if ($alias) {
            $formatter = 'formatBody'.ucfirst($alias);
            if (method_exists($this, $formatter)) {
                return $this->{$formatter}($body);
            }
        }
        if (is_scalar($body)) {
            return $this->body = (string) $body;
        }
        if (is_array($body)) {
            return $this->body = enjson($body);
        }
        if (is_object($body)) {
            if ($body instanceof Response) {
                return $this->__send();
            }

            if (method_exists($body, '__toString')) {
                return $this->body = $this->stringBody($body->__toString());
            }
            if (method_exists($body, '__toArray')) {
                return $this->body = $this->stringBody($body->__toArray());
            }
        }

        $this->status = 500;
        return $this->body = '__UNSTRINGABLE_RESPONSE__';
    }

    public function formatBodyXml($body = null)
    {
        $xml = enxml($body ?: $this->body);
        if (true !== ($error = is_xml($xml))) {
            $this->setStatus(500)->setMimeAlias('json');

            return $this->body = enxml([
                'error' => 'InvalidOriginAsXML',
                'extra' => stringify($body),
            ]);
        }
            
        return $this->body = $xml;
    }

    public function formatBodyJson($body = null)
    {
        return $this->body = enjson($body ?: $this->body);
    }

    public function setMimeAlias(string $alias = null)
    {
        $this->mime = self::$mimes[$alias] ?? 'text/html';

        return $this;
    }

    public function getMime()
    {
        return $this->mime;
    }

    public function setMime(string $mime = null)
    {
        $this->mime = $mime;

        return $this;
    }

    public function getStatus() : int
    {
        return $this->status;
    }

    public function setStatus(int $status)
    {
        $this->status = $status;

        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    public function getInfo()
    {
        return $this->info;
    }

    public function setInfo(string $info)
    {
        $this->info = $info;

        return $this;
    }

    public function setError(bool $error)
    {
        $this->error = $error;

        return $this;
    }

    public function getError()
    {
        return $this->error;
    }

    public function hasError() : bool
    {
        return $this->error === true;
    }

    public function isFailed(int $success = null) : bool
    {
        return !$this->isSuccess();
    }

    public function isSuccess(int $success = null) : bool
    {
        if ($success) {
            return $this->status === $success;
        }
        return (100 <= $this->status) && ($this->status < 400);
    }

    public function getWrapouts()
    {
        return $this->wrappers['out'] ?? [];
    }

    public function getWraperrs()
    {
        return $this->wrappers['err'] ?? [];
    }

    public function getWraperr(string $key)
    {
        return $this->wrappers['err'][$key] ?? null;
    }

    public function addWraperr(string $key, $value)
    {
        $this->wrappers['err'][$key] = $value;

        return $this;
    }

    public function getWrapout(string $key)
    {
        return $this->wrappers['out'][$key] ?? null;
    }

    public function addWrapout(string $key, $value)
    {
        $this->wrappers['out'][$key] = $value;

        return $this;
    }

    public function getWrapper(string $key, string $type)
    {
        $tyep = strtolower($type);
        if (! in_array($type, ['err', 'out'])) {
            return null;
        }

        return $this->wrappers[$type][$key] ?? null;
    }

    public function __descruct()
    {
        $this->__send();
    }

    public function getContext() : array
    {
        return [
            $this->getStatus(),
            $this->getInfo(),
            $this->getMimeAlias(),
            $this->getError(),
        ];
    }
}
