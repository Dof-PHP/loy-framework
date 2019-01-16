<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Http;

use Loy\Framework\Web\Http\Http;

class Request
{
    use Http;

    public function get(string $key)
    {
        return 'getting '.$key;
    }

    public function getDomain() : ?string
    {
        return $_SERVER['HTTP_HOST'] ?? null;
    }

    public function getMime() : ?string
    {
        return $this->getMimeShort();
    }

    public function getMimeShort() : ?string
    {
        if (! ($mime = $this->getMimeLong())) {
            return null;
        }
        $mime  = explode(';', $mime);
        $short = $mime[0] ?? false;

        return ($short === false) ? null : trim($short);
    }

    public function getMimeLong() : ?string
    {
        $mime = $_SERVER['HTTP_CONTENT_TYPE'] ?? false;

        return $mime ? trim(strtolower($mime)) : null;
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
        if (! $mime) {
            return false;
        }

        $_mime = (self::$mimes[$alias] ?? false);
        if (! $_mime) {
            return false;
        }

        if ($this->getMimeShort() === $_mime) {
            return true;
        }

        return false;
    }

    public function __get(string $key)
    {
        $method = 'get'.ucfirst($key);
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }
    }
}
