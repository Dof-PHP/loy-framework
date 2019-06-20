<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\DDD\Model;
use Dof\Framework\Queue\Job;
use Dof\Framework\OFB\Traits\Enqueuable;

/**
 * Notes:
 * - Event properties must un-private
 *
 * - No exception should be thrown when handling async event
 *   Since it will broken the execution of business code
 *   We logging all errors and exceptions happens in queuing process
 */
abstract class Event extends Model implements Job
{
    use Enqueuable;

    const DEFAULT_QUEUE = 'default';
    const EVENT_QUEUE = 'event';

    final public function publish()
    {
        $meta = self::meta();

        $listeners = $meta['LISTENER'] ?? [];
        if (! $listeners) {
            return;
        }

        $event = static::class;

        if (confirm($meta['SYNC'] ?? '1')) {
            $this->broadcast($listeners);
            return;
        }

        $driver = ConfigManager::getDomainFinalEnvByNamespace($event, 'EVENT_QUEUE_DRIVER');

        $this->enqueue($event, $driver);
    }

    /**
     * Broadcast event as queue job
     */
    final public function execute()
    {
        $this->broadcast();
    }

    final public function broadcast(array $listeners = null)
    {
        if (is_null($listeners)) {
            $listeners = self::listeners();
        }
        if (! $listeners) {
            return;
        }

        foreach ($listeners as $listener) {
            $listener::init()->setEvent($this)->handle();
        }
    }

    public function formatQueueName() : string
    {
        $domain = DomainManager::getKeyByNamespace(static::class);
        $class = objectname($this);
        if ($domain && $class) {
            $key = join('_', [$domain, $class]);
        } else {
            $key = self::DEFAULT_QUEUE;
        }

        return strtolower(join(':', [self::EVENT_QUEUE, $key]));
    }

    final public static function listeners() : array
    {
        return self::meta()['LISTENER'] ?? [];
    }

    final public static function meta() : array
    {
        $annotations = EventManager::get(static::class);
        if (! $annotations) {
            return [];
        }

        return (array) $annotations['meta'] ?? [];
    }
}
