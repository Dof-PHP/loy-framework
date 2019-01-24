<?php

declare(strict_types=1);

namespace Loy\Framework\Base\Exception;

use Throwable;
use Exception;

class BaseBaseException extends Exception
{
    public function __construct(string $message, int $code = 500, Throwable $previous = null)
    {
        $this->message = $message;
        $this->code    = $code;
    }
}
