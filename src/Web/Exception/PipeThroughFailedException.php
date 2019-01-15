<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class PipeThroughFailedException extends BaseWebException
{
    public function __construct(string $pipe, int $code = 400)
    {
        parent::__construct($pipe, $code);
    }
}
