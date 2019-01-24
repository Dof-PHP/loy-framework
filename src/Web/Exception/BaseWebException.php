<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Throwable;
use Exception;
use Loy\Framework\Web\Response;

class BaseWebException extends Exception
{
    public function __construct(string $message, int $code, Throwable $previous = null)
    {
        $this->message = $message;
        $this->code    = $code;

        $_previous = $previous ? $previous->__toString() : '';

        Response::setStatus($this->code);
        Response::send([
            $this->code,
            join(' => ', [objectname($this), $this->message]),
            $_previous
        ], true);
    }
}
