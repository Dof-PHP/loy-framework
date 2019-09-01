<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Wrapper;

class Full
{
    public function wraperr()
    {
        return [
            '__DATA__' => 'data',
            'code' => 0,
            'info' => 'ok',
            'more',
            'meta'
        ];
    }

    public function wrapout()
    {
        return [
            '__DATA__' => 'data',
            'code' => 0,
            '__INFO__' => 'info',
            'more',
            'meta'
        ];
    }
}
