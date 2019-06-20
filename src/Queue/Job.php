<?php

declare(strict_types=1);

namespace Dof\Framework\Queue;

interface Job
{
    public function execute();
}
