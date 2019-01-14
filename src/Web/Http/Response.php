<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Http;

use Loy\Framework\Web\Http\Http;

class Response
{
    use Http;

    private $body    = '';
    private $status  = 200;
    private $mime    = 'text/html';
    private $headers = [];

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
        $this->body    = $body;
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
        echo (string) $this->body;
        exit(0);
    }

    private function stringBody($body)
    {
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

    public function setMimeAlias(string $alias = null)
    {
        if (! is_null($alias)) {
            if ($alias === '_') {
                $this->mime = false;
            }
            $this->mime = self::$mimes[$alias] ?? 'text/html';
        }

        return $this;
    }

    public function setMime(string $mime)
    {
        $this->mime = $mime;

        return $this;
    }

    public function setStatus(int $status)
    {
        $this->status = $status;

        return $this;
    }

    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    public function __descruct()
    {
        $this->__send();
    }
}
