<?php

declare(strict_types=1);

namespace Dof\OFB\Pipe;

use Throwable;
use Dof\Framework\Facade\Response;
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
            Response::abort(401, ERR::MISSING_AUTH_TOKEN_HEADER);
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
            Response::abort(401, ERR::INVALID_TOKEN_IN_HEADER, ['type' => 1], $port->get('class'));
        }
        $data = array_trim_from_string($data, "\n");
        $data = array_values($data);
        if (count($data) !== 10) {
            Response::abort(401, ERR::INVALID_TOKEN_IN_HEADER, ['type' => 2], $port->get('class'));
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

        try {
            $result = singleton(HttpHmac::class)::setSignature($signature)
                ->setSecret($this->getSecret($client, $port->get('class')))
                ->setVersion($version)
                ->setImplementor($implementor)
                ->setAlgorithm($algorithm)
                ->setRealm($realm)
                ->setClient($client)
                ->setTimestamp($timestamp)
                ->setNonce($nonce)
                ->setParameters($parameters)
                ->setMore($more)
                ->setHost($host)
                ->setVerb($verb)
                ->setPath($path)
                ->setHeaders($headers)
                ->verify();

            if ($result !== true) {
                Response::abort(401, ERR::INVALID_TOKEN_SIGNATURE, [], $port->get('class'));
            }
        } catch (Throwable $e) {
            Response::abort(401, ERR::HTTP_HMAC_TOKEN_VEFIY_FAILED, [], $port->get('class'));
        }
    }

    abstract public function getSecret(string $client, $domain) : string;
}
