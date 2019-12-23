<?php

declare(strict_types=1);

namespace DOF\Traits;

use DOF\ErrManager;
use DOF\Util\Exceptor;

trait ExceptorThrow
{
    // Use throw() to handle server side error/exceptions only
    // For example: server resource is not properly configured, dependencies unavailable, etc
    final public function throw(...$params)
    {
        $exceptor = new Exceptor(...$params);

        $exceptor->setProxy(true)->setChain(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        throw $exceptor;
    }

    // Use err() to handle client side error/exceptions only
    // For example: parameters invalid, permission denied, etc
    final public function err(array $err, array $context = [], Throwable $previous = null)
    {
        $exceptor = new Exceptor($previous);

        $code = $err[0] ?? -1;

        throw $exceptor
            ->setProxy(true)
            ->tag(Exceptor::TAG_CLIENT)
            ->setNo($code)
            ->setInfo($err[1] ?? null)
            ->setSuggestion($err[2] ?? null)
            ->setName(ErrManager::name($code))
            ->setContext($context)
            ->setChain(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
    }
}
