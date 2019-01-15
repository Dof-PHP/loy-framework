<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class PortNotExistsException extends BaseWebException
{
    public function __construct(string $ns, int $code = 500)
    {
        parent::__construct($ns, $code);
    }
}
