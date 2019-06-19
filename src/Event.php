<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\DDD\Model;

/**
 * Notes: Event properties must un-private
 */
abstract class Event extends Model
{
    const EVENT_QUEUE = 'event';

    final public function publish()
    {
        $annotations = EventManager::get(static::class);
        if (! $annotations) {
            return;
        }
        $listeners = $annotations['meta']['LISTENER'] ?? [];
        if (! $listeners) {
            return;
        }

        $sync = $annotations['meta']['SYNC'] ?? '1';
        if (confirm($sync)) {
            foreach ($listeners as $listener) {
                $listener::init()->setEvent($this)->handle();
            }

            return;
        }

        // Check queue config in the domain of current event class
        // TODO
    }
}
