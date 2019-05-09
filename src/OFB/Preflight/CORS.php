<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Preflight;

/**
 * CORS control at server side
 */
class CORS
{
    public function preflight($request, $response)
    {
        // NOTES: It's might not working if your web server has limited reqeust methods before php
        $headers = [
            'Access-Control-Allow-Origin'   => join(',', $this->getAccessControlAllowOrigin()),
            'Access-Control-Expose-Headers' => join(',', $this->getAccessControlExposeHeaders()),
            'Access-Control-Allow-Methods'  => join(',', $this->getAccessControlAllowMethods()),
            'Access-Control-Allow-Headers'  => join(',', $this->getAccessControlAllowHeaders()),
            'Access-Control-Max-Age' => $this->getAccessControlMaxAge(),
            'Access-Control-Allow-Credentials' => 'true',
        ];

        if (ci_equal($request->getMethod(), 'OPTIONS')) {
            return $response
                ->setMimeAlias('text')
                ->setBody(null)
                ->setStatus(204)
                ->setError(false)
                ->setHeaders($headers)
                ->send();
        }

        $response->setHeaders($headers);

        return true;
    }

    private function getAccessControlAllowOrigin() : array
    {
        return [
            '*',
        ];
    }

    private function getAccessControlExposeHeaders() : array
    {
        return [
            'APIUCID',
        ];
    }

    private function getAccessControlAllowHeaders() : array
    {
        return [
            '*',
            'Access-Control-Allow-Origin',
            'AUTHORIZATION',
            'Content-Type',
            'Accept',
            'APIUCID',
        ];
    }

    private function getAccessControlAllowMethods() : array
    {
        return [
            '*',
            'OPTIONS',
            'GET',
            'HEAD',
            'POST',
            'PATCH',
            'PUT',
            'DELETE',
            // 'CONNECT',
            // 'TRACE',
        ];
    }

    private function getAccessControlMaxAge() : int
    {
        return 604800;
    }
}
