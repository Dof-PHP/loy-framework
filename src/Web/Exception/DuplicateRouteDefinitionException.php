<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class DuplicateRouteDefinitionException extends BaseWebException
{
    public function __construct(string $route, int $code = 500)
    {
        paren::__construct($route, $code);
    }
}
