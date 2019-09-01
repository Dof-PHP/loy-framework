<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Auth;

/**
 * Keyed-hash message authentication code
 */
class HMAC
{
    /** @var string: The version string of hmac */
    protected $version = '1.0';    // #1

    /** @var string: The key of implementor of HMAC */
    protected $implementor = 'dof-php-hmac';    // #2

    /** @var string: Name of selected hashing algorithm (hash_hmac_algos()) */
    protected $algorithm = 'sha256';    // #3

    /** @var string: Key of Message provider */
    protected $realm;    // #4

    /** @var string: The unique identifier or acceess key of client  */
    protected $client;     // #5

    /** @var int: The timestamp generated message signature */
    protected $timestamp;    // #6
 
    /** @var string: The Nonce string of the message */
    protected $nonce;    // #7

    /** @var array: The biz parameters */
    protected $parameters = [];    // #8

    /** @var array: The extra parameters */
    protected $more = [];    // #9

    /** @var string: The secret key for signature */
    protected $secret;

    /** @var string: The message signature raw string */
    protected $signature;

    /** @var int : Default signature timeout seconds */
    private $timeout = 600;

    /** @var int: Default timeout deviation allowed between servers */
    private $timeoutDeviation = 10;

    /** @var bool: Verify timeout or not */
    private $timeoutCheck = true;

    /** @var bool: Verify timestamp or not */
    private $timestampCheck = true;

    /**
     * Validate a message string signature
     *
     * @return bool
     */
    public function verify() : bool
    {
        if (! $this->signature) {
            exception('MissingSignatureToValidate');
        }

        if ($this->sign() === $this->signature) {
            $current = time();
            if ($this->timestampCheck && ($this->timestamp > ($current + $this->timeoutDeviation))) {
                exception('InvalidTimestampCompareToCurrentTime');
            }
            $timeout = $this->timeout();
            if ($this->timeoutCheck && ($current >= ($this->timestamp + $timeout))) {
                exception('TimeoutedHMACSignature', compact('timeout'));
            }

            return true;
        }

        return false;
    }

    /**
     * Overwrite this method to control signature expiration if you need
     */
    public function timeout() : int
    {
        return $this->timeout;
    }

    /**
     * Sign a message string
     *
     * @return string: The signature of current message
     */
    public function sign() : string
    {
        $this->prepare();

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
        $this->realm = urlencode($realm);
    
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
        $this->client = urlencode($client);
    
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
        $this->nonce = urlencode($nonce);
    
        return $this;
    }

    /**
     * Getter for timestamp
     *
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
    
    /**
     * Setter for timestamp
     *
     * @param int $timestamp
     * @return HMAC
     */
    public function setTimestamp(int $timestamp)
    {
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

    /**
     * Getter for timeout
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
    
    /**
     * Setter for timeout
     *
     * @param int $timeout
     * @return HMAC
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
    
        return $this;
    }

    /**
     * Setter for timeoutDeviation
     *
     * @param int $timeoutDeviation
     * @return HMAC
     */
    public function setTimeoutDeviation(int $timeoutDeviation)
    {
        $this->timeoutDeviation = $timeoutDeviation;
    
        return $this;
    }

    /**
     * Setter for timeoutCheck
     *
     * @param bool $timeoutCheck
     * @return HMAC
     */
    public function setTimeoutCheck(bool $timeoutCheck)
    {
        $this->timeoutCheck = $timeoutCheck;
    
        return $this;
    }

    /**
     * Setter for timestampCheck
     *
     * @param bool $timestampCheck
     * @return HMAC
     */
    public function setTimestampCheck(bool $timestampCheck)
    {
        $this->timestampCheck = $timestampCheck;
    
        return $this;
    }
}
