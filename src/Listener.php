<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Facade\Log;
use Dof\Framework\Queue\Job;
use Dof\Framework\OFB\Traits\Enqueuable;
use Dof\Framework\OFB\Traits\DI;

abstract class Listener implements Job
{
    use Enqueuable;
    use DI;

    const DEFAULT_QUEUE = 'default';
    const LISTENER_QUEUE = 'listener';
    const LISTENER_QUEUE_DRIVER = 'LISTENER_QUEUE_DRIVER';
    const LISTENER_ASYNC = 'ASYNC_LISTENER';

    protected $event;

    public function handle()
    {
        $listener = static::class;

        $async = ConfigManager::getDomainEnvByNamespace($listener, self::LISTENER_ASYNC, []);
        if ((! $async) || (! array_key_exists($listener, $async))) {
            $this->execute();
            return;
        }

        $driver = ConfigManager::getDomainFinalEnvByNamespace($listener, self::LISTENER_QUEUE_DRIVER);

        $partition = $async[$listener] ?? 0;
        if (! is_int($partition)) {
            Log::log('listener-error', 'InvalidAsyncListenerPartitionInteger', compact('partition'));
            return;
        }

        $this->__partition = $partition;

        $this->enqueue($listener, $driver);
    }

    abstract public function execute();

    final public static function init()
    {
        return Container::di(static::class);
    }

    final public function setEvent(Event $event)
    {
        $this->event = $event;

        return $this;
    }

    final public function formatQueueName(string $listener) : string
    {
        if (ConfigManager::getDomainFinalEnvByNamespace($listener, 'DISABLE_QUEUE_FORMATTING', false)) {
            return self::DEFAULT_QUEUE;
        }

        $domain = DomainManager::getKeyByNamespace($listener);
        $class = classname($listener);
        if ($domain && $class) {
            $key = join('_', [$domain, $class]);
        } else {
            $key = self::DEFAULT_QUEUE;
        }

        if ($this->__partition > 0) {
            $key = join('_', [$key, $this->__partition($this->__partition)]);
        }

        return strtolower(join(':', [self::LISTENER_QUEUE, $key]));
    }
}
