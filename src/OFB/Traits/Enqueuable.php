<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Traits;

use Dof\Framework\Kernel;
use Dof\Framework\QueueManager;

trait Enqueuable
{
    /** @var uint: Async queue parition number, also size of queue workers */
    protected $__partition = 0;

    public function enqueue(string $namespace, string $driver = null)
    {
        $queue = $this->formatQueueName($namespace);

        try {
            $queuable = QueueManager::get($namespace, $queue, $driver);
        } catch (Throwable $e) {
            $exception = parse_throwable($e);
            $sapi = Kernel::getSapiContext(true);
            Log::log('queue-exception', 'ExceptionCaughtWhenGettingQueuable', compact(
                'namespace',
                'sapi',
                'exception'
            ));
            return;
        }

        if (! $queuable) {
            $sapi = Kernel::getSapiContext(true);
            Log::log('queue-error', 'CouldNotFoundQueuableInDomain', compact('namespace', 'sapi'));
            return;
        }

        $queue = QueueManager::formatQueueName($queue);

        try {
            $queuable->enqueue($queue, $this);
        } catch (Throwable $e) {
            $exception = parse_throwable($e);
            $sapi = Kernel::getSapiContext(true);
            Log::log('queue-exception', 'ExceptionCaughtWhenEnqueueJob', compact(
                'namespace',
                'sapi',
                'exception'
            ));
        }
    }

    /**
     * Format a queue name from a domain class namespace
     *
     * @param string $namespace: Domain class
     * @return string: Formatted queue name
     */
    public function formatQueueName(string $namespace) : string
    {
        $ns = array_trim_from_string($namespace, '\\');
        if (count($ns) > 1) {
            unset($ns[0]);
        }
        if (! $ns) {
            return 'default';
        }

        $queue = strtolower(join(':', $ns));

        if ($this->__partition > 0) {
            $queue = join('_', [$queue, $this->__partition($this->__partition)]);
        }

        return $queue;
    }

    final public function __partition(int $__partition) : int
    {
        if ($__partition < 1) {
            return $__partition;
        }

        if (true
            && method_exists($this, 'partition')
            && is_int($_partition = $this->partition())
            && ($_partition > 0)
        ) {
            $partition = $_partition;
        } else {
            $partition = time();
        }

        return $partition % $__partition;
    }

    public function __setPartition(int $__partition)
    {
        $this->__partition = $__partition;

        return $this;
    }
}
