<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Traits;

use Dof\Framework\Kernel;
use Dof\Framework\QueueManager;

trait Enqueuable
{
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

        $queue = join(':', [QueueManager::QUEUE_NORMAL, $queue]);

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

        return $ns ? strtolower(join(':', $ns)) : 'default';
    }
}
