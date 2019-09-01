<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Dof\Framework\OFB\Wrapper\ActionOnly;
use Dof\Framework\OFB\Wrapper\Classic;

class DynamicRestWrapout
{
    /**
     * Select a wrapout according to the http verb
     *
     * !!! Warning: Using this pipein will not be documented
     */
    public function pipein($request, $response, $route, $port)
    {
        $verb = $request->getMethod();

        switch ($verb) {
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $port->set('wrapout', ActionOnly::class);
                $port->set('wraperr', ActionOnly::class);
                break;
            case 'GET':
            case 'POST':
            default:
                $port->set('wrapout', Classic::class);
                $port->set('wraperr', Classic::class);
                break;
        }

        return true;
    }
}
