<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Wrapper;

class Simple
{
    public function wraperr()
    {
        return ['code' => 600, 'info' => 'UNKNOWN_ERROR'];
    }

    public function wrapout()
    {
        return ['__DATA__' => 'data', 'code' => 0, 'info' => 'ok'];
    }
}
