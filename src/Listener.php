<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Facade\Log;
use Dof\Framework\Queue\Job;
use Dof\Framework\OFB\Traits\Enqueuable;

abstract class Listener implements Job
{
    use Enqueuable;

    const DEFAULT_QUEUE = 'default';
    const LISTENER_QUEUE = 'listener';

    protected $event;

    public function handle()
    {
        $listener = static::class;

        $async = ConfigManager::getDomainEnvByNamespace($listener, 'ASYNC_LISTENER', []);
        if ((! $async) || (! array_key_exists($listener, $async))) {
            $this->execute();
            return;
        }

        $driver = ConfigManager::getDomainFinalEnvByNamespace($listener, 'LISTENER_QUEUE_DRIVER');

        $partition = $async[$listener] ?? 0;
        if (! is_int($partition)) {
            Log::log('listener-error', 'InvalidAsyncListenerPartitionInteger', compact('partition'));
            return;
        }

        self::$__partition = $partition;

        $this->enqueue($listener, $driver);
    }

    abstract public function execute();

    final public static function init()
    {
        return Container::di(static::class);
    }

    final public function di(string $namespace)
    {
        return Container::di($namespace);
    }

    final public function setEvent(Event $event)
    {
        $this->event = $event;

        return $this;
    }

    final public function formatQueueName(string $listener) : string
    {
        $domain = DomainManager::getKeyByNamespace(static::class);
        $class = objectname($this);
        if ($domain && $class) {
            $key = join('_', [$domain, $class]);
        } else {
            $key = self::DEFAULT_QUEUE;
        }

        if (self::$__partition > 0) {
            $key = join('_', [$key, $this->__partition(self::$__partition)]);
        }

        return strtolower(join(':', [self::LISTENER_QUEUE, $key]));
    }
}
