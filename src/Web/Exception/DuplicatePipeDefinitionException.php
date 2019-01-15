<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;
use Loy\Framework\Web\Response;

class DuplicatePipeDefinitionException extends Exception
{
    public function __construct(string $route, int $code = 500)
    {
        $this->message = $route;
        $this->code    = $code;

        $error = objectname($this).': '.$this->message;

        Response::setBody($error)->setStatus($this->code)->send();
    }
}
