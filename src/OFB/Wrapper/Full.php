<?php

declare(strict_types=1);

namespace Loy\Framework\OFB\Wrapper;

class Full
{
    public function wrapout()
    {
        return ['data', 'status' => 200, 'message' => 'ok', 'meta', 'extra'];
    }
}
