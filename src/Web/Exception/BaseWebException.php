<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;
use Loy\Framework\Web\Response;

class BaseWebException extends Exception
{
    public function __construct(string $message, int $code, string $lastTrace = null)
    {
        $this->message = $message;
        $this->code    = $code;

        $lastTrace= $lastTrace ? explode(PHP_EOL, $this->lastTrace) : [];

        Response::setStatus($this->code);
        Response::send([$this->code, join(' => ', [objectname($this), $this->message]), $lastTrace], true);
    }
}
