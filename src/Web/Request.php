<?php

declare(strict_types=1);

namespace Dof\Framework\Web;

use Dof\Framework\Web\Route;
use Dof\Framework\ConfigManager;

class Request
{
    use HttpTrait;

    /**
     * Match a list of field and return if found the first one
     *
     * @param array $keys: Fields list to match aginst
     * @param string $_key: The first matched field name
     * @return mixed|null
     */
    public function match(array $keys = [], string &$_key = null)
    {
        foreach ($keys as $key) {
            if (! is_scalar($key)) {
                continue;
            }
            $key = (string) $key;
            $val = $this->all($key);
            if (! is_null($val)) {
                $_key = $key;
                return $val;
            }
        }

        return null;
    }

    public function only(...$keys) : array
    {
        $params = $keys;
        $cnt = count($keys);
        if ($cnt === 1 && is_array($_keys = ($keys[0] ?? false))) {
            $params = $_keys;
        }
        if (! $params) {
            return [];
        }

        $all = $this->all();
        $res = [];
        foreach ($params as $key) {
            if (! is_string($key)) {
                continue;
            }
            if (array_key_exists($key, $all)) {
                $res[$key] = $all[$key];
            }
        }

        return $res;
    }

    public function isNotGet() : bool
    {
        return $this->getVerb() !== 'GET';
    }

    public function isGet() : bool
    {
        return $this->getVerb() === 'GET';
    }

    public function isPost() : bool
    {
        return $this->getVerb() === 'POST';
    }

    public function all(string $key = null, $default = null, array $rules = null)
    {
        return $key ? array_get($this->getAll(), $key, $default, $rules) : $this->getAll();
    }

    public function input(string $key = null, $default = null, array $rules = null)
    {
        $input = $this->getInput();

        return $key ? (is_array($input) ? array_get($input, $key, $default, $rules) : null) : $input;
    }

    public function post(string $key = null, $default = null, array $rules = null)
    {
        return $key ? array_get((array) $this->getPost(), $key, $default, $rules) : $this->getPost();
    }

    public function get(string $key = null, $default = null, array $rules = null)
    {
        return $key ? array_get($this->getGet(), $key, $default, $rules) : $this->getGet();
    }

    public function getInput()
    {
        return $this->getOrSet('input', function () {
            $input = $this->getInputRaw();
            $mime  = $this->getMimeAlias();

            return $this->convertStringAsMime($input, $mime);
        });
    }

    public function getInputRaw() : string
    {
        return $this->getOrSet('inputRaw', function () {
            return urldecode(trim((string) file_get_contents('php://input')));
        });
    }

    public function getAll() : array
    {
        return $this->getOrSet('all', function () {
            $input = $this->getInput();
            if (is_array($input)) {
                return array_merge($_REQUEST, $input);
            }

            return $_REQUEST;
        });
    }

    public function getPost() : array
    {
        return $this->getOrSet('post', function () {
            return $_POST;
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
        $mime = $this->getMimeShort();

        return ($this->isNotGet() && (! $mime)) ? 'form' : $mime;
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
        $mime = $_SERVER['HTTP_CONTENT_TYPE'] ?? null;

        return $mime ? trim(strtolower($mime)) : null;
    }

    public function getVerb() : ?string
    {
        return $this->getMethod();
    }

    public function getIp() : ?string
    {
        return $this->getOrSet('ip', function () {
            return http_client_ip();
        });
    }

    public function getUA() : ?string
    {
        return $this->getOrSet('ua', function () {
            return $this->getHeader('USER_AGENT');
        });
    }

    public function getHeader(string $keyupper) : ?string
    {
        $keyupper = strtoupper($keyupper);
        $headers  = $this->getHeaders();

        return $headers[$keyupper] ?? ($headers[str_replace('-', '_', $keyupper)] ?? null);
    }

    public function hasHeader(string $keyupper) : bool
    {
        return isset($this->getHeaders()[strtoupper($keyupper)]);
    }

    public function getHeaders() : array
    {
        return $this->getOrSet('headers', function () {
            return array_change_key_case(getallheaders(), CASE_UPPER);
        });
    }

    public function getMethod() : ?string
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
            $res = (string) parse_url("http://dof{$uri}", PHP_URL_QUERY);

            return $res;
        });
    }

    public function getScheme() : string
    {
        return $_SERVER['REQUEST_SCHEME'] ?? '?';
    }

    public function getUrl() : string
    {
        return $this->getOrSet('url', function () {
            return sprintf('%s://%s%s', $this->getScheme(), $this->getHost(), $this->getRequestUri());
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
            $uri = (string) parse_url("http://dof{$uri}", PHP_URL_PATH);

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

    public function getContext() : array
    {
        $context = [
            $this->getVerb(),
            $this->getUri(),
            $this->getMime(),
        ];

        $logHeaders = ConfigManager::getEnv('HTTP_REQUEST_LOG_HEADERS', false);
        $logParams = ConfigManager::getEnv('HTTP_REQUEST_LOG_PARAMS', false);
        $domain = Route::get('class');
        if ($domain) {
            $logParams = ConfigManager::getDomainFinalEnvByNamespace($domain, 'HTTP_REQUEST_LOG_PARAMS', false);
            $logHeaders = ConfigManager::getDomainFinalEnvByNamespace($domain, 'HTTP_REQUEST_LOG_HEADERS', false);
        }

        $log = [];
        if ($logParams) {
            $params = $this->getAll();
            $maskKeys = $domain
            ? ConfigManager::getDomainFinalDomainByNamespace(
                $domain,
                'HTTP_REQUEST_PARAMS_MASK_KEYS',
                ['password']
            )
            : ConfigManager::getDomain(
                'HTTP_REQUEST_PARAMS_MASK_KEYS',
                ['password']
            );

            foreach ($params as $key => &$val) {
                if (ciin_array($key, $maskKeys)) {
                    $val = '*';
                }
            }

            $log[0] = $params;
        }
        if ($logHeaders) {
            $log[1] = $this->getHeaders();
        }
        if ($log) {
            $context[] = $log;
        }

        return $context;
    }
}
