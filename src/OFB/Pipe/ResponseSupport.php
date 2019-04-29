<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Dof\Framework\DDD\Service;
use Dof\Framework\Paginator;
use Dof\Framework\Web\Response;

/**
 * Recognize supported result types and set particular attributes to properties of current response
 */
class ResponseSupport
{
    public function pipeout($result, $route, $port, $request, $response)
    {
        if ($result instanceof Response) {
            return $result->send();
        }

        if ($result instanceof Service) {
            return $result->execute();
        }

        if ($result instanceof Paginator) {
            $meta = $result->getMeta();
            $response->addWrapout('paginator', $meta);

            // Should not return $result->getList() for customizing paginator later
            // Paginator is special, here we're not convert it into array
            return $result;
        }

        return $result;
    }
}
