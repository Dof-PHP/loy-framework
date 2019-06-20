<?php

declare(strict_types=1);

namespace Dof\Framework\Queue;

interface Queuable
{
    public function enqueue(string $queue, Job $job);

    public function dequeue(string $queue);
}
