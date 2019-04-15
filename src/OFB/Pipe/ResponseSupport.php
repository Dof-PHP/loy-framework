<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Dof\Framework\DDD\ApplicationService;
use Dof\Framework\Paginator;

class ResponseSupport
{
    /**
     * Recognize supported result types and set particular attributes to properties of current response
     *
     * @param mixed $result: Responding result
     */
    public function pipeout($result, $route, $request, $response)
    {
        if ($result instanceof ApplicationService) {
            if (! $result->__isExecuted()) {
                $result->exec();
            }

            return $result->__getData();
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
