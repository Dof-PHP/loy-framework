<?php

declare(strict_types=1);

namespace Loy\Framework\OFB\Wrapper;

class Pagination
{
    public function wrapout()
    {
        return ['__DATA__' => 'data', 'status' => 200, 'message' => 'ok', '__PAGINATOR__' => 'page'];
    }
}
