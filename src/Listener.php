<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Queue\Job;
use Dof\Framework\OFB\Traits\Enqueuable;

abstract class Listener implements Job
{
    use Enqueuable;

    const DEFAULT_QUEUE = 'default';
    const LISTENER_QUEUE = 'listener';

    protected $event;
    protected $sync = true;

    public function handle()
    {
        if ($this->sync) {
            $this->execute();
            return;
        }

        $listener = static::class;

        $driver = ConfigManager::getDomainFinalEnvByNamespace($listener, 'LISTENER_QUEUE_DRIVER');

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

    public function formatQueueName(string $listener) : string
    {
        $domain = DomainManager::getKeyByNamespace(static::class);
        $class = objectname($this);
        if ($domain && $class) {
            $key = join('_', [$domain, $class]);
        } else {
            $key = self::DEFAULT_QUEUE;
        }

        return strtolower(join(':', [self::LISTENER_QUEUE, $key]));
    }
}
