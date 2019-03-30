<?php

declare(strict_types=1);

namespace Loy\Framework\OFB\Wrapper;

class Service
{
    public function wrapout()
    {
        return ['status' => 200, 'message' => 'ok', 'data', 'meta', 'extra'];
    }
}
