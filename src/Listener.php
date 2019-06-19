<?php

declare(strict_types=1);

namespace Dof\Framework;

abstract class Listener
{
    const LISTENER_QUEUE = 'listener';

    protected $event;

    final public function init()
    {
        return Container::di(static::class);
    }

    abstract public function handle();

    final public function setEvent(Event $event)
    {
        $this->event = $event;

        return $this;
    }
}
