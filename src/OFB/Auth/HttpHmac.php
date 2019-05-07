<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Auth;

class HttpHmac extends HMAC
{
    protected $implementor = 'dof-php-http-hmac';

    /** @var string: Request host */
    private $host;    #10

    /** @var string: Request verb */
    private $verb;    #11

    /** @var string: Request path */
    private $path;    #12

    /** @var array: Request Headers */
    private $headers = [];    #13

    public function prepare()
    {
        parent::prepare();

        if (! $this->verb) {
            exception('MissingHttpHmacVerb');
        }
        if (! $this->host) {
            exception('MissingHttpHmacHost');
        }
        if (! $this->path) {
            exception('MissingHttpHmacPath');
        }
    }

    public function build()
    {
        $this->prepare();

        return join("\n", [
            $this->version,
            $this->implementor,
            $this->algorithm,
            $this->realm,
            $this->client,
            $this->timestamp,
            $this->nonce,
            $this->stringify($this->parameters),
            $this->stringify($this->more),
            $this->host,
            $this->verb,
            $this->path,
            $this->stringify($this->headers),
        ]);
    }

    /**
     * Getter for headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
    
    /**
     * Setter for headers
     *
     * @param array $headers
     * @return HttpHmac
     */
    public function setHeaders(array $headers)
    {
        foreach ($headers as $key => $val) {
            if (is_int($key)) {
                exception('BadHeaderKey', compact('key'));
            }
            if (! is_string($val)) {
                exception('BadHeaderValue', compact('val'));
            }
        }

        $this->headers = $headers;
    
        return $this;
    }

    /**
     * Getter for verb
     *
     * @return string
     */
    public function getVerb(): string
    {
        return $this->verb;
    }
    
    /**
     * Setter for verb
     *
     * @param string $verb
     * @return HttpHmac
     */
    public function setVerb(string $verb)
    {
        $this->verb = $verb;
    
        return $this;
    }

    /**
     * Getter for host
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }
    
    /**
     * Setter for host
     *
     * @param string $host
     * @return HttpHmac
     */
    public function setHost(string $host)
    {
        $this->host = $host;
    
        return $this;
    }

    /**
     * Getter for path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }
    
    /**
     * Setter for path
     *
     * @param string $path
     * @return HttpHmac
     */
    public function setPath(string $path)
    {
        $this->path = $path;
    
        return $this;
    }
}
