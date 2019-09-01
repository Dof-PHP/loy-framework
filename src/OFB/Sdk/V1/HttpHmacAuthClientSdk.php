<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Sdk\V1;

use Exception;

/**
 * HttpHmac认证 客户端SDK v1
 */
class HttpHmacAuthClientSdk
{
    // 系统固定参数
    private $version = '1.0';
    private $implementor = 'dof-php-http-hmac';
    private $algorithm = 'sha256';

    // 服务自定义必须参数
    private $secret;
    private $realm;
    private $client;
    private $timestamp;
    private $nonce;
    private $host;
    private $verb;
    private $path;

    // 服务自定义可选参数
    private $parameters = [];

    // 保留参数 暂不使用
    private $headers = [];
    private $more = [];

    /**
     * 生成 Authorization 请求头 http-hmac 认证方式需要用的 token 值
     */
    public function token() : string
    {
        $signature = $this->sign();

        return base64_encode(join("\n", [
            $this->version,
            $this->implementor,
            $this->algorithm,
            $this->realm,
            $this->client,
            $this->timestamp,
            $this->nonce,
            $this->stringify($this->more),
            $this->stringify($this->headers),
            $signature
        ]));
    }

    public function sign() : string
    {
        $this->prepare();

        return hash_hmac($this->algorithm, $this->build(), $this->secret);
    }

    public function prepare()
    {
        if (! $this->secret) {
            $this->throw('MissingClientSecret');
        }
        if (! $this->realm) {
            $this->throw('MissingClientRealm');
        }
        if (! $this->client) {
            $this->throw('MissingClientId');
        }
        if (! $this->timestamp) {
            $this->throw('MissingTimestamp');
        }
        if (! $this->nonce) {
            $this->throw('MissingNonceString');
        }
        if (! $this->host) {
            $this->throw('MissingHttpApiHost');
        }
        if (! $this->verb) {
            $this->throw('MissingHttpApiMethod');
        }
        if (! $this->path) {
            $this->throw('MissingHttpApiPath');
        }
    }

    public function stringify(array $data) : string
    {
        if (! $data) {
            return '';
        }

        $data = array_change_key_case($data, CASE_LOWER);

        ksort($data);

        return http_build_query($data);
    }

    public function build()
    {
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

    public function throw(string $name, array $context = [])
    {
        $throw = $context
        ? json_encode([$name, $context], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : $name;

        throw new Exception($throw);
    }

    public function setSecret(string $secret)
    {
        $this->secret = $secret;
    
        return $this;
    }

    public function setRealm(string $realm)
    {
        $this->realm = urlencode($realm);
    
        return $this;
    }

    public function setClient(string $client)
    {
        $this->client = urlencode($client);
    
        return $this;
    }

    public function setTimestamp(int $timestamp)
    {
        $this->timestamp = (string) $timestamp;
    
        return $this;
    }

    public function setNonce(string $nonce)
    {
        $this->nonce = urlencode($nonce);
    
        return $this;
    }

    public function setHost(string $host)
    {
        $this->host = urlencode($host);
    
        return $this;
    }

    public function setVerb(string $verb)
    {
        $this->verb = strtoupper($verb);

        return $this;
    }

    public function getVerb(): string
    {
        return $this->verb;
    }

    public function setPath(string $path)
    {
        $this->path = urlencode($path);
    
        return $this;
    }

    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    
        return $this;
    }
}
