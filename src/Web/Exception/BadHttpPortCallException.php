<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Response;
use Exception;

class BadHttpPortCallException extends Exception
{
    public function __construct(string $call, int $code = 500)
    {
        $this->message = $call;
        $this->code    = $code;

        $error = join(':', [objectname($this), $this->message]);

        Response::setMimeAlias('text')
            ->setBody($error)
            ->setStatus($this->code)
            ->send();
    }
}
