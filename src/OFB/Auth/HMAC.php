<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Auth;

/**
 * Keyed-hash message authentication code
 */
class HMAC
{
    /** @var string: The version string of hmac */
    protected $version = '1.0';

    /** @var string: The key of implementor of HMAC */
    protected $implementor = 'dof-php-hmac';

    /** @var string: Name of selected hashing algorithm (hash_hmac_algos()) */
    protected $algorithm = 'sha256';

    /** @var string: Key of Message provider */
    protected $realm;

    /** @var string: The unique identifier or acceess key of client  */
    protected $client;

    /** @var string: The Nonce string of the message */
    protected $nonce;

    /** @var string: The timestamp generated message signature */
    protected $timestamp;
 
    /** @var array: The biz parameters */
    protected $parameters = [];

    /** @var array: The extra parameters */
    protected $more = [];

    /** @var string: The secret key for signature */
    protected $secret = 'to-be-replaced';

    /** @var string: The message signature raw string */
    protected $signature;

    /**
     * Validate a message string signature
     *
     * @return bool
     */
    public function validate() : bool
    {
        if (! $this->signature) {
            exception('MissingSignatureToValidate');
        }

        return $this->sign() === $this->signature;
    }

    /**
     * Sign a message string
     *
     * @return string: The signature of current message
     */
    public function sign() : string
    {
        return hash_hmac($this->algorithm, $this->build(), $this->secret);
    }

    public function prepare()
    {
        if (! $this->version) {
            exception('MissingHMACVersion');
        }
        if (! $this->implementor) {
            exception('MissingHMACImplementor');
        }
        if (! $this->algorithm) {
            exception('MissingHMACAlgorithm');
        }
        if (! $this->realm) {
            exception('MissingHMACMessageRealm');
        }
        if (! $this->client) {
            exception('MissingHMACMessageClient');
        }
        if (! $this->nonce) {
            exception('MissingHMACMessageNonce');
        }
        if (! $this->timestamp) {
            exception('MissingHMACMessageTimestamp');
        }
        if (! $this->secret) {
            exception('MissingSecretForSignature');
        }
    }

    /**
     * Build all types of parameters to a message string
     *
     * @return string: The message string
     */
    public function build()
    {
        $this->prepare();

        return join(PHP_EOL, [
            $this->version,
            $this->implementor,
            $this->algorithm,
            $this->realm,
            $this->client,
            $this->timestamp,
            $this->nonce,
            $this->stringify($this->parameters),
            $this->stringify($this->more),
        ]);
    }

    /**
     * Build array data to string
     *
     * @param array $data
     */
    public function stringify(array $data) : string
    {
        if (! $data) {
            return '';
        }

        $data = array_change_key_case($data, CASE_LOWER);

        ksort($data);

        return http_build_query($data);
    }

    public function toArray()
    {
        return $this->__toArray();
    }

    public function __toArray()
    {
        $arr = get_object_vars($this);

        $arr['secret'] = '*';

        return $arr;
    }

    /**
     * Getter for version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Setter for version
     *
     * @param string $version
     * @return HMAC
     */
    public function setVersion(string $version)
    {
        $this->version = $version;
    
        return $this;
    }

    /**
     * Getter for implementor
     *
     * @return string
     */
    public function getImplementor(): string
    {
        return $this->implementor;
    }
    
    /**
     * Setter for implementor
     *
     * @param string $implementor
     * @return HMAC
     */
    public function setImplementor(string $implementor)
    {
        $this->implementor = $implementor;
    
        return $this;
    }

    /**
     * Getter for algorithm
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }
    
    /**
     * Setter for algorithm
     *
     * @param string $algorithm
     * @return HMAC
     */
    public function setAlgorithm(string $algorithm)
    {
        if (! in_array($algorithm, hash_algos())) {
            exception('UnSupportedAlgorithm', compact('algorithm'));
        }
    
        $this->algorithm = $algorithm;

        return $this;
    }

    /**
     * Getter for realm
     *
     * @return string
     */
    public function getRealm(): string
    {
        return $this->realm;
    }
    
    /**
     * Setter for realm
     *
     * @param string $realm
     * @return HMAC
     */
    public function setRealm(string $realm)
    {
        $this->realm = $realm;
    
        return $this;
    }

    /**
     * Getter for client
     *
     * @return string
     */
    public function getClient(): string
    {
        return $this->client;
    }
    
    /**
     * Setter for client
     *
     * @param string $client
     * @return HMAC
     */
    public function setClient(string $client)
    {
        $this->client = $client;
    
        return $this;
    }

    /**
     * Getter for nonce
     *
     * @return string
     */
    public function getNonce(): string
    {
        return $this->nonce;
    }

    /**
     * Setter for nonce
     *
     * @param string $nonce
     * @return HMAC
     */
    public function setNonce(string $nonce)
    {
        $this->nonce = $nonce;
    
        return $this;
    }

    /**
     * Getter for timestamp
     *
     * @return string
     */
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }
    
    /**
     * Setter for timestamp
     *
     * @param string $timestamp
     * @return HMAC
     */
    public function setTimestamp(string $timestamp)
    {
        if (! is_timestamp($timestamp)) {
            exception('BadHMACMessageTimestamp', compact('timestamp'));
        }

        $this->timestamp = $timestamp;
    
        return $this;
    }

    /**
     * Getter for parameters
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
    
    /**
     * Setter for parameters
     *
     * @param array $parameters
     * @return HMAC
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    
        return $this;
    }

    /**
     * Getter for more
     *
     * @return array
     */
    public function getMore(): array
    {
        return $this->more;
    }
    
    /**
     * Setter for more
     *
     * @param array $more
     * @return HMAC
     */
    public function setMore(array $more)
    {
        $this->more = $more;
    
        return $this;
    }
    
    /**
     * Getter for secret
     *
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }
    
    /**
     * Setter for secret
     *
     * @param string $secret
     * @return HMAC
     */
    public function setSecret(string $secret)
    {
        $this->secret = $secret;
    
        return $this;
    }

    /**
     * Getter for signature
     *
     * @return string
     */
    public function getSignature(): string
    {
        return $this->signature;
    }
    
    /**
     * Setter for signature
     *
     * @param string $signature
     * @return HMAC
     */
    public function setSignature(string $signature)
    {
        $this->signature = $signature;
    
        return $this;
    }
}
