<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Throwable;
use Exception;
use Loy\Framework\Web\Response;

class BaseWebException extends Exception
{
    public function __construct(string $message = '', int $code = 500, Throwable $previous = null)
    {
        $this->message = $message;
        $this->code    = $code;

        $objectname = objectname($this);
        $_previous  = $previous ? $previous->__toString() : '';
        $message    = $message ?join(' => ', [$objectname, $this->message]) : $objectname;

        Response::setStatus($this->code);
        Response::send([
            $this->code,
            $message,
            $_previous
        ], true);
    }
}
