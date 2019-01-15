<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class InvalidRequestMimeException extends BaseWebException
{
    public function __construct(string $mimes, int $code = 400)
    {
        parent::__construct($mimes, $code);
    }
}
