<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class DuplicateRouteAliasDefinitionException extends BaseWebException
{
    public function __construct(string $alias, int $code = 500)
    {
        parent::__construct($alias, $code);
    }
}
