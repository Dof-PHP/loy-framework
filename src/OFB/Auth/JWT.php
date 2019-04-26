<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Auth;

use Closure;

/**
 * Json Web Token
 */
class JWT
{
    /** @var int: Time of JWT token to live */
    private $ttl = 604800;    // One week

    /** @var string: The secret key for signature */
    private $key = 'to-be-replaced';    // FIXME

    /** @var string: Name of selected hashing algorithm (hash_hmac_algos()) */
    private $algo = 'sha256';

    private $beforeIssue;
    private $afterIssue;

    private $beforeVerify;
    private $afterVerify;

    /**
     * Issue a jwt token by given params and key
     *
     * @param array $params: User defined payload
     */
    public function issue(...$params)
    {
        if ($this->beforeIssue && (true === ($res = $this->beforeIssue()))) {
            exception('BeforeIssueHookFailed', compact('res'));
        }

        $header = $this->encode([
            'typ' => 'JWT',
            'alg' => $this->algo,
        ]);
        $timestamp = time();
        $claims = [
            'iss' => 'dof',    // Issuer
            'exp' => $timestamp + $this->ttl,    // Expiration Time
            // 'sub' => null,    // Subject
            // 'aud' => null,    // Audience
            // 'jti' => null,    // JWT ID
            'nbf' => $timestamp,    // Not Before
            'iat' => $timestamp,    // Issued At
            'tza' => date('T'),     // Timezone abbreviation (custom)
        ];

        $payload   = $this->encode([$claims, unsplat(...$params)]);
        $signature = $this->sign(join('.', [$header, $payload]), $this->algo, $this->key);

        $token = join('.', [$header, $payload, $signature]);

        if ($this->afterIssue && (true !== ($res = $this->afterIssue($token, $params)))) {
            exception('AfterIssueHookFailed', compact('res'));
        }

        return $token;
    }

    /**
     * Verify a JWT token signed by dof
     *
     * @param string $token
     * @throw
     * @return array: User defined payload
     */
    public function verify(string $token)
    {
        if (! $token) {
            exception('MissingToken');
        }

        if ($this->beforeVerify && (true !== ($res = $this->beforeVerify($token)))) {
            exception('BeforeVerifyHookFailed', compact('res'));
        }

        $arr = explode('.', $token);
        $cnt = count($arr);
        if (3 !== $cnt) {
            exception('InvalidTokenComponentCount', compact('cnt'));
        }
        $header = $arr[0] ?? null;
        if (! ($header) || (! is_string($header))) {
            exception('InvalidTokenHeader', compact('header'));
        }
        $payload= $arr[1] ?? null;
        if (! ($payload) || (! is_string($payload))) {
            exception('InvalidTokenPayload', compact('payload'));
        }
        $signature = $arr[2] ?? null;
        if (! ($signature) || (! is_string($signature))) {
            exception('BadTokenSignature', compact('signature'));
        }
        $_header = $this->decode($header, true);
        if ((! $_header) || (! is_array($_header)) || (! ($alg = ($_header['alg'] ?? false)))) {
            exception('InvalidTokenHeader', compact('_header'));
        }
        $alg = strtolower($alg);
        if (! in_array($alg, hash_algos())) {
            exception('UnSupportedAlgorithm', compact('alg'));
        }
        if ($signature !== $this->sign(join('.', [$header, $payload]), $alg, $this->key)) {
            exception('InvalidTokenSignature', compact('signature'));
        }
        $data = $this->decode($payload, true);
        $tza = $data[0]['tza'] ?? null;
        if ((! $tza) || (! ci_equal($tza, date('T')))) {
            exception('InvalidTokenTimezone', compact('tza'));
        }
        $exp = $data[0]['exp'] ?? null;
        if ((! $exp) || (! is_timestamp($exp))) {
            exception('InvalidTokenExpireTime', compact('exp'));
        }
        if (time() > $exp) {
            exception('ExpiredToken', compact('exp', 'tza'));
        }

        $params = $data[1] ?? [];
        if ($this->afterVerify && (true !== ($res = $this->afterVerify($params)))) {
            exception('AfterVerifyHookFailed', compact('res'));
        }

        return $params;
    }

    /**
     * Authorise a jwt token
     *
     * @param mixed|{Closure|string|null} $token
     */
    public function authorise($token = null)
    {
        if ($token) {
            if (is_closure($token)) {
                $token = $token();
            }
        } else {
            $token = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        }

        return $this->verify($token);
    }

    /**
     * Sign a text string with jwt flavor
     *
     * @param string $text
     * @param string $algo
     * @param string $secret
     * @return string
     * @throw
     */
    public function sign(string $text, string $algo, string $secret)
    {
        if (! in_array($algo, hash_algos())) {
            exception('UnSupportedAlgorithm', compact('algo'));
        }

        return enbase64(hash_hmac($algo, $text, $secret), true);
    }

    /**
     * Decode a jwt token into php structure
     *
     * @param string $token
     * @param bool $array: Return as array or not
     */
    public function decode(string $token, bool $array = true)
    {
        return json_decode(debase64($token, true), $array);
    }

    /**
     * Encode a php array into jwt flavor token
     *
     * @param array $data
     */
    public function encode(array $data)
    {
        return enbase64(json_encode($data), true);
    }

    public function setAfterVerify(Closure $hook)
    {
        $this->afterVerify = $hook;

        return $this;
    }

    public function setBeforeVerify(Closure $hook)
    {
        $this->beforeVerify = $hook;

        return $this;
    }

    public function setBeforeIssue(Closure $hook)
    {
        $this->beforeIssue = $hook;

        return $this;
    }

    public function setAfterIssue(Closure $hook)
    {
        $this->afterIssue = $hook;

        return $this;
    }

    public function setKey(string $key)
    {
        $this->key = $key;

        return $this;
    }

    public function setTTL(int $ttl)
    {
        $this->ttl = $ttl;

        return $this;
    }

    public function setAlgo(string $algo)
    {
        $this->algo = strtolower($algo);

        return $this;
    }
}
