<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class WrapperInNotExistsException extends BaseWebException
{
    public function __construct(string $wrapin, int $code = 500)
    {
        parent::__construct($wrapin, $code);
    }
}
