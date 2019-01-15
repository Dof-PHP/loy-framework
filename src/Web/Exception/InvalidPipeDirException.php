<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class InvalidPipeDirException extends BaseWebException
{
    public function __construct(string $dir, int $code = 500)
    {
        parent::__construct($dir, $code);
    }
}
