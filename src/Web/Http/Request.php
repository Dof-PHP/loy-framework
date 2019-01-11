<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Http;

use Loy\Framework\Web\Http\Http;

class Request
{
    use Http;

    public function getMime() : ?string
    {
        return $_SERVER['HTTP_CONTENT_TYPE'] ?? '?';
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

    public function isMimeAlias(string $alias) : bool
    {
        $mime = $this->getMime();
        if ('?' === $mime) {
            return false;
        }

        $_mime = (self::$mimes[$alias] ?? false);
        if (! $_mime) {
            return false;
        }

        $mime = explode(';', $mime);
        if (($mime[0] ?? false) === $_mime) {
            return true;
        }

        return false;
    }
}
