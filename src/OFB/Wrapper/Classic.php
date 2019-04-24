<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Wrapper;

class Classic
{
    public function wraperr()
    {
        return ['code', 'info', 'more'];
    }

    public function wrapout()
    {
        return [
            '__DATA__' => 'data',
            'code' => 0,
            'info' => 'ok',
            'more',
            'meta'
        ];
    }
}
