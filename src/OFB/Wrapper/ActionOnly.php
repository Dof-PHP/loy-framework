<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Wrapper;

class ActionOnly
{
    public function wraperr()
    {
        return ['code', 'info'];
    }

    public function wrapout()
    {
        return ['code' => 0, 'info' => 'ok'];
    }
}