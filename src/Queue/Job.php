<?php

declare(strict_types=1);

namespace Dof\Framework\Queue;

interface Job
{
    public function execute();

    // public function onStarted();
    // public function onFinished();
    // public function onFailed();
}
