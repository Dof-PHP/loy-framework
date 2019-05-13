<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Auth;

use Closure;
use Throwable;

/**
 * Json Web Token
 */
class JWT
{
    /** @var int: Time of JWT token to live */
    private $ttl;

    /** @var int: The secret id for signature */
    private $secretId;

    /** @var string: The secret key for signature */
    private $secretKey;

    /** @var string: Name of selected hashing algorithm (hash_hmac_algos()) */
    private $algo = 'sha256';

    private $beforeIssue;
    private $afterIssue;

    // callbacks
    private $beforeVerify;
    private $afterVerify;
    private $onTokenVerifyExpired;

    public function prepare()
    {
        if ((! is_int($this->ttl)) || ($this->ttl < 1)) {
            exception('BadTokenTTLSetting', ['ttl' => $this->ttl]);
        }
        if ((! $this->secretId) || (! is_scalar($this->secretId))) {
            exception('MissingOrInvalidSecretId', ['id' => $this->secretId]);
        }
        if ((! $this->secretKey) || (! is_string($this->secretKey))) {
            exception('MissingOrInvalidSecretKey', ['key' => $this->secretKey]);
        }
    }

    public function issue(...$params)
    {
        $this->prepare();

        if ($this->beforeIssue && (true === ($res = ($this->beforeIssue)()))) {
            exception('BeforeIssueHookFailed', compact('res'));
        }

        $header = $this->encode([
            'typ' => 'JWT',
            'alg' => $this->algo,
        ]);
        $ts = time();
        $claims = [
            'iss' => 'dof',    // Issuer
            // 'sub' => null,  // Subject
            // 'aud' => null,  // Audience
            // 'jti' => null,  // JWT ID
            'nbf' => $ts,      // Not Before
            'iat' => $ts,      // Issued At
            'sid' => $this->secretId,     // JWT secret key ID
            'tza' => date('T'),           // Timezone abbreviation (custom)
            'exp' => $ts + $this->ttl,    // Expiration Time
        ];

        $payload   = $this->encode([$claims, unsplat(...$params)]);
        $signature = $this->sign(join('.', [$header, $payload]), $this->algo, $this->secretKey);

        $token = join('.', [$header, $payload, $signature]);

        if ($this->afterIssue) {
            try {
                $res = ($this->afterIssue)($token, unsplat(...$params));
            } catch (Throwable $e) {
                exception('AfterIssueHookFailed', compact('res'), $e);
            }
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
        if (! $this->secretKey) {
            exception('MissingTokenSecret');
        }

        if ($this->beforeVerify && (true !== ($res = ($this->beforeVerify)($token)))) {
            exception('BeforeVerifyHookFailed', compact('res'));
        }

        $arr = explode('.', $token);
        $cnt = count($arr);
        if (3 !== $cnt) {
            exception('InvalidTokenComponentCount', compact('cnt'));
        }
        $header = $arr[0] ?? null;
        if (! ($header) || (! is_string($header))) {
            exception('MissingOrBadTokenHeader', compact('header'));
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
        if (! in_array($alg, hash_algos())) {
            exception('UnSupportedAlgorithm', compact('alg'));
        }
        if ($signature !== $this->sign(join('.', [$header, $payload]), $alg, $this->secretKey)) {
            exception('InvalidJwtTokenSignature', compact('signature'));
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
        $params = $data[1] ?? null;
        if (time() > $exp) {
            if ($this->onTokenVerifyExpired) {
                try {
                    ($this->onTokenVerifyExpired)($token, $params);
                } catch (Throwable $e) {
                    exception('onTokenVerifyExpiredCallbackException', [], $e);
                }
            }

            exception('ExpiredToken', compact('exp', 'tza'));
        }

        if ($this->afterVerify) {
            try {
                $res = ($this->afterVerify)($params, $token);
            } catch (Throwable $e) {
                exception('AfterVerifyHookFailed', compact('res'), $e);
            }
        }

        return $params;
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

    public function setOnTokenVerifyExpired(Closure $hook)
    {
        $this->onTokenVerifyExpired = $hook;

        return $this;
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

    public function setSecretId(int $id)
    {
        $this->secretId = $id;

        return $this;
    }

    public function setSecretKey(string $key)
    {
        $this->secretKey = $key;

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
