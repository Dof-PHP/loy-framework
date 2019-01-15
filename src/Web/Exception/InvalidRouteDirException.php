<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Loy\Framework\Web\Exception\BaseWebException;

class InvalidRouteDirException extends BaseWebException
{
    public function __construct(string $dir, int $code = 500)
    {
        parent::__construct($dir, $code);
    }
}
