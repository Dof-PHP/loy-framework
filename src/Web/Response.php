<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

use Dof\Framework\Kernel;

class Response
{
    use HttpTrait;

    /** @var bool: User defined response error status */
    private $error = false;

    /** @var int: HTTP response status code */
    private $status = 200;

    /** @var string: HTTP response body content */
    private $body;

    /** @var bool: Response instance is sent or not */
    private $sent = false;

    /** @var string: HTTP response Content-Type short name */
    private $mime = 'text/html';

    /** @var array: Response body structure elements candidates (KV) */
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

    public function send()
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

        echo $this->body;

        $this->sent = true;

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
        if (is_null($body)) {
            return $this->body = '';
        }
        if (is_scalar($body)) {
            return $this->body = (string) $body;
        }
        if (is_array($body)) {
            return $this->body = enjson($body);
        }
        if (is_object($body)) {
            if ($body instanceof Response) {
                return $this->send();
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
        $this->body = $this->stringBody($body);

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

    public function addHeader(string $key, $val)
    {
        $this->headers[$key] = $val;

        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    public function getWraperr(string $key, $default = null)
    {
        return $this->wrappers['err'][$key] ?? $default;
    }

    public function addWraperr(string $key, $value)
    {
        $this->wrappers['err'][$key] = $value;

        return $this;
    }

    public function getWrapout(string $key, $default = null)
    {
        return $this->wrappers['out'][$key] ?? $default;
    }

    public function addWrapout(string $key, $value)
    {
        $this->wrappers['out'][$key] = $value;

        return $this;
    }

    public function getWrapper(string $key, string $type, $default = null)
    {
        if (! ciin($type, ['err', 'out'])) {
            return null;
        }

        return $this->wrappers[$type][$key] ?? $default;
    }

    public function __descruct()
    {
        $this->send();
    }

    public function getServerName()
    {
        return $_SERVER['SERVER_NAME'] ?? null;
    }

    public function getServerOS()
    {
        return PHP_OS;
    }

    public function getServerInfo()
    {
        return php_uname();
    }

    public function getServerIP()
    {
        return $_SERVER['SERVER_ADDR'] ?? null;
    }

    public function getServerPort()
    {
        return $_SERVER['SERVER_PORT'] ?? null;
    }

    public function getContext() : array
    {
        return $this->sent
        ? [
            $this->getStatus(),
            $this->getMimeAlias(),
            $this->getError(),
        ] : [Kernel::getError() ? 500 : 200, 'html', false];
    }
}
