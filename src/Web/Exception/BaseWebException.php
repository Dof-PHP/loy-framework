<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;

class BaseWebException extends Exception
{
    public function __construct(string $message, int $code)
    {
        $this->message = $message;
        $this->code    = $code;

        $error = objectname($this).': '.$this->message;

        Response::setBody($error)->setStatus($this->code)->send();
    }
}
