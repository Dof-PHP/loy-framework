<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Wrapper;

class Pagination
{
    public function wrapout()
    {
        return [
            'code' => 0,
            'info' => 'ok',
            '__DATA__' => 'data',
            '__PAGINATOR__' => 'page',
            'more'
        ];
    }
}
