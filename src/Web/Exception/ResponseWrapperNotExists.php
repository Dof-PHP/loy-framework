<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class ResponseWrapperNotExists extends BaseWebException
{
    public function __construct(string $wrapper, int $code = 500)
    {
        parent::__construct($wrapper, $code);
    }
}
