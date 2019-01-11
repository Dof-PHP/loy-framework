<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;
use ReflectionClass;
use Loy\Framework\Web\Response;

class InvalidRequestMimeException extends Exception
{
    public function __construct(string $mimes, int $code = 400)
    {
        $this->message = $mimes;
        $this->code    = $code;

        $error = strtoupper((new ReflectionClass($this))->getShortName()).': '.$this->message;

        Response::setBody($error)->setStatus($this->code)->send();
    }
}
