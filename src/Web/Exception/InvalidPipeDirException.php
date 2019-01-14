<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;
use ReflectionClass;
use Loy\Framework\Web\Response;

class InvalidPipeDirException extends Exception
{
    public function __construct(string $dir, int $code = 500)
    {
        $this->message = $dir;
        $this->code    = $code;

        $error = (new ReflectionClass($this))->getShortName().': '.$this->message;

        Response::setBody($error)->setStatus($this->code)->send();
    }
}
