<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;
use ReflectionClass;
use Loy\Framework\Web\Response;

class PipeThroughFailedException extends Exception
{
    public function __construct(string $pipe, int $code = 404)
    {
        $this->message = $pipe;
        $this->code    = $code;

        $error = (new ReflectionClass($this))->getShortName().': '.$this->message;

        Response::setBody($error)->setStatus($this->code)->send();
    }
}
