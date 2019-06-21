<?php

declare(strict_types=1);

namespace Dof\Framework\Queue;

use Closure;

class Worker
{
    public static function new(Closure $onFailed)
    {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            $onFailed();
            return null;
        }

        return $pid;
    }

    public static function process(Job $job, Closure $onException) : bool
    {
        try {
            $job->execute();
        } catch (Throwable $e) {
            $onException($e);

            return false;
        }

        return true;
    }
}
