<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

class BearerAuth
{
    public function pipein($request, $response, $route, $port)
    {
        $auth = $request->getHeader('AUTHORIZATION');

        // TODO

        return false;
    }
}
