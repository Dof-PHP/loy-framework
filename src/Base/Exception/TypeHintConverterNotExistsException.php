<?php

declare(strict_types=1);

namespace Loy\Framework\Base\Exception;

use Exception;

class TypeHintConverterNotExistsException extends Exception
{
    public function __construct(string $message, int $code = 500)
    {
        $this->message = $message;
        $this->code    = $code;
    }
}
