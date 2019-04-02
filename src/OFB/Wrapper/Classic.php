<?php

declare(strict_types=1);

namespace Loy\Framework\OFB\Wrapper;

class Classic
{
    public function wraperr()
    {
        return ['code', 'info', 'extra'];
    }

    public function wrapout()
    {
        return ['__DATA__' => 'data', 'status' => 200, 'message' => 'ok', 'extra'];
    }
}
