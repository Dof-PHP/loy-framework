<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Http;

use Loy\Framework\Web\Http\Http;

class Request
{
    use Http;

    public function input(string $key = null)
    {
        $input = $this->getInput();

        if (is_array($input)) {
            return $key ? ($input[$key] ?? null) : $input;
        }

        return $key ? null : $input;
    }

    public function get(string $key)
    {
        $get = $this->getGet();

        return $get[$key] ?? null;
    }

    public function getInput()
    {
        return $this->getOrSet('input', function () {
            $input = file_get_contents('php://input');
            // $mime  = $this->getMimeShort();
            // $this->convertInputToArray($input, $mime);

            return $input;
        });
    }

    public function getGet() : array
    {
        return $this->getOrSet('get', function () {
            return $_GET;
        });
    }

    public function getDomain() : ?string
    {
        return $this->getOrSet('domain', function () {
            $host = $this->getHost();
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return $host;
            }
            $arr = explode('.', $host);
            $cnt = count($arr);
            if ($cnt === 1) {
                return $host;
            }

            $domain = '';
            for ($i = $cnt - 1; $i >= 0; --$i) {
                if ($i == ($cnt - 1)) {
                    $domain = $arr[$i];
                    continue;
                }
                if ($i == ($cnt - 2)) {
                    $domain = $arr[$i].'.'.$domain;
                    continue;
                }

                break;
            }

            return $domain;
        });
    }

    public function getHost() : ?string
    {
        return $this->getOrSet('host', function () {
            return $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? null);
        });
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
        return $this->getOrSet('method', function () {
            return $_SERVER['REQUEST_METHOD'] ?? null;
        });
    }

    public function getQueryString() : string
    {
        return $this->getOrSet('queryString', function () {
            $res = $_SERVER['QUERY_STRING'] ?? null;
            if (! is_null($res)) {
                return $res;
            }

            $uri = $this->getRequestUri();
            $res = (string) parse_url("http://loy{$uri}", PHP_URL_QUERY);

            return $res;
        });
    }

    public function getUri() : string
    {
        return $this->getOrSet('uri', function () {
            $uri = $this->getUriRaw();
            $uri = join('/', array_filter(explode('/', $uri)));

            return $uri ?: '/';
        });
    }

    public function getRequestUri() : string
    {
        return $this->getOrSet('uriRequest', function () {
            return urldecode($_SERVER['REQUEST_URI'] ?? '/');
        });
    }

    public function getUriRaw() : string
    {
        return $this->getOrSet('uriRaw', function () {
            $uri = $this->getRequestUri();
            $uri = (string) parse_url("http://loy{$uri}", PHP_URL_PATH);

            return $uri;
        });
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
