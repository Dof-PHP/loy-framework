<?php

declare(strict_types=1);

namespace Loy\Framework\OFB\Wrapper;

class Pagination
{
    public function wrapout()
    {
        return ['__DATA__' => 'data', 'code' => 0, 'info' => 'ok', '__PAGINATOR__' => 'page', 'more'];
    }
}
