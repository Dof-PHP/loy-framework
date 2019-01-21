<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class FrameworkCoreException extends BaseWebException
{
    public function __construct(string $error, int $code = 500, string $lastTrace = null)
    {
        parent::__construct($error, $code, $lastTrace);
    }
}
