<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Http;

class Request
{
    public function getContentType() : string
    {
        return ($_SERVER['HTTP_CONTENT_TYPE'] ?? 'text/plain');
    }

    public function getMethod() : string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    }

    public function getUri() : string
    {
        $uri = self::getUriRaw();
        $uri = join('/', array_filter(explode('/', $uri)));

        return $uri ?: '/';
    }

    public function getUriRaw() : string
    {
        return urldecode($_SERVER['REQUEST_URI'] ?? 'UNKNOWN');
    }
}
