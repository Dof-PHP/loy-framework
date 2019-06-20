<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\DDD\Model;
use Dof\Framework\Facade\Log;
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
    const EVENT_QUEUE_DRIVER = 'EVENT_QUEUE_DRIVER';
    const EVENT_ASYNC = 'ASYNC_EVENT';

    final public function publish()
    {
        $listeners = self::listeners();
        if (! $listeners) {
            return;
        }

        $event = static::class;
        $async = ConfigManager::getDomainEnvByNamespace($event, self::EVENT_ASYNC, []);
        if ((! $async) || (! array_key_exists($event, $async))) {
            $this->broadcast($listeners);
            return;
        }

        $partition = $async[$event] ?? 0;
        if (! is_int($partition)) {
            Log::log('event-error', 'InvalidAsyncEventPartitionInteger', compact('partition'));
            return;
        }

        $this->__partition = $partition;

        $driver = ConfigManager::getDomainFinalEnvByNamespace($event, self::EVENT_QUEUE_DRIVER);

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

    final public function formatQueueName(string $event) : string
    {
        if (ConfigManager::getDomainFinalEnvByNamespace($event, 'DISABLE_QUEUE_FORMATTING', false)) {
            return self::DEFAULT_QUEUE;
        }

        $domain = DomainManager::getKeyByNamespace($event);
        $class = classname($event);
        if ($domain && $class) {
            $key = join('_', [$domain, $class]);
        } else {
            $key = self::DEFAULT_QUEUE;
        }

        if ($this->__partition > 0) {
            $key = join('_', [$key, $this->__partition($this->__partition)]);
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
