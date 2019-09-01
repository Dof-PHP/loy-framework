<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Throwable;
use Dof\Framework\Facade\Response;
use Dof\Framework\EXCP;
use Dof\Framework\Web\ERR;
use Dof\Framework\OFB\Auth\HttpHmac;

/**
 * AUTHORIZATION: http-hmac {token}
 */
abstract class HttpHmacAuth
{
    public function pipein($request, $response, $route, $port)
    {
        $header = trim((string) $request->getHeader('AUTHORIZATION'));
        if (! $header) {
            Response::abort(401, ERR::MISSING_HTTP_HMAC_TOKEN_HEADER);
        }
        if (! ci_equal(mb_substr($header, 0, 10), 'http-hmac ')) {
            Response::abort(401, ERR::INVALID_HTTP_HMAC_TOKEN, [], $port->get('class'));
        }

        $token = mb_substr($header, 10);
        if (! $token) {
            Response::abort(401, ERR::MISSING_TOKEN_IN_HEADER, [], $port->get('class'));
        }

        $data = base64_decode($token);
        if (! is_string($data)) {
            Response::abort(401, ERR::INVALID_TOKEN_IN_HEADER, ['err' => 'Non-string token raw'], $port->get('class'));
        }
        $data = explode("\n", $data);
        $data = array_values($data);
        if (count($data) !== 10) {
            Response::abort(401, ERR::INVALID_TOKEN_IN_HEADER, ['err' => 'Params count mis-match'], $port->get('class'));
        }
        list(
            $version,
            $implementor,
            $algorithm,
            $realm,
            $client,
            $timestamp,
            $nonce,
            $more,
            $headers,
            $signature
        ) = $data;
        if (! $signature) {
            Response::abort(401, ERR::MISSING_SIGNATURE_IN_TOKEN, [], $port->get('class'));
        }

        $verb = $route->get('verb');
        $host = $request->getHost();
        $path = $request->getUriRaw();
        $params = $request->all();

        $_more = [];
        parse_str(urldecode($more), $_more);
        $_headers = [];
        parse_str(urldecode($headers), $_headers);

        try {
            $result = singleton(HttpHmac::class)
                ->setSignature($signature)
                ->setSecret($this->getSecret($realm, $client, $port->get('class')))
                ->setVersion($version)
                ->setImplementor($implementor)
                ->setAlgorithm($algorithm)
                ->setRealm($realm)
                ->setClient($client)
                ->setTimestamp(intval($timestamp))
                ->setNonce($nonce)
                ->setParameters($params)
                ->setMore($_more)
                ->setHost($host)
                ->setVerb($verb)
                ->setPath($path)
                ->setHeaders($_headers)
                ->setTimeoutCheck(boolval($this->timeoutCheck()))
                ->setTimeoutDeviation(intval($this->timeoutDeviation()))
                ->setTimestampCheck(boolval($this->timestampCheck()))
                ->verify();

            if ($result !== true) {
                Response::abort(401, ERR::INVALID_HTTP_HMAC_TOKEN_SIGNATURE, [], $port->get('class'));
            }

            return true;
        } catch (Throwable $e) {
            $excp = ERR::HTTP_HMAC_TOKEN_VERIFY_FAILED;
            if (EXCP::is($e, EXCP::TIMEOUTED_HMAC_SIGNATURE)) {
                $excp = ERR::TIMEOUTED_HMAC_SIGNATURE;
            }

            $context = [];
            $context = parse_throwable($e, $context);
            Response::abort(401, $excp, $context, $port->get('class'));
        }
    }

    public function timeoutCheck()
    {
        return true;
    }

    public function timeoutDeviation()
    {
        return 30;
    }

    public function timestampCheck()
    {
        return true;
    }

    /**
     * Get Http Hmac auth client secret
     *
     * @param string $relam: Client Realm
     * @param string $client: AppId
     * @param string $domain: domain class namespace
     *
     * @return string : AppKey
     */
    abstract public function getSecret(string $realm, string $client, string $domain) : string;
}
