<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class InvalidUrlParameterException extends BaseWebException
{
    public function __construct(string $error, int $code = 400)
    {
        parent::__construct($error, $code);
    }
}
