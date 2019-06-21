<?php

declare(strict_types=1);

namespace Dof\Framework\Queue;

interface Queuable
{
    public function enqueue(string $queue, Job $job);

    public function dequeue(string $queue) :? Job;

    public function restart(string $queue);

    public function setRestart(string $queue) : bool;

    public function needRestart(string $queue) : bool;
}
