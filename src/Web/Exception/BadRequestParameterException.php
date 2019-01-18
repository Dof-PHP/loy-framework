<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class BadRequestParameterException extends BaseWebException
{
    public function __construct(string $call, int $code = 400)
    {
        parent::__construct($call, $code);
    }
}
