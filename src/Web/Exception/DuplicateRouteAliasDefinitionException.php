<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;
use ReflectionClass;
use Loy\Framework\Web\Response;

class DuplicateRouteAliasDefinitionException extends Exception
{
    public function __construct(string $alias, int $code = 500)
    {
        $this->message = $alias;
        $this->code    = $code;

        $error = (new ReflectionClass($this))->getShortName().': '.$this->message;

        Response::setBody($error)->setStatus($this->code)->send();
    }
}
