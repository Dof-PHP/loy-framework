<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class BadRouteWrapperInExecutionException extends BaseWebException
{
    public function __construct(string $call, int $code = 500)
    {
        parent::__construct($call, $code);
    }
}
