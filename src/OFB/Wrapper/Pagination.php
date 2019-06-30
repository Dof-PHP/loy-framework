<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Wrapper;

class Pagination
{
    public function wrapout()
    {
        return [
            '__DATA__' => 'data',
            '__PAGINATOR__' => 'page',
            'code' => 0,
            '__INFO__' => 'ifo',
            'more',
            // 'meta'
        ];
    }
}
