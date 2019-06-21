<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Queue\Queuable;
use Dof\Framework\Storage\Redis;
use Dof\Framework\Storage\Connection;
use Dof\Framework\OFB\Traits\PartitionString;

final class QueueManager
{
    use PartitionString;

    const QUEUE_NORMAL = '__DOF_QUEUE_NORMAL';
    const QUEUE_LOCKED = '__DOF_QUEUE_LOCKED';
    const QUEUE_FAILED = '__DOF_QUEUE_FAILED';
    const QUEUE_TIMEOUT = '__DOF_QUEUE_TIMEOUT';
    const QUEUE_RESTART = '__DOF_QUEUE_RESTART';

    const QUEUE_DRIVER = 'QUEUE_DRIVER';

    const SUPPORT_DRIVERS = [
        'redis' => Redis::class,
    ];

    /** @var array: Domain class Namespace <=> Queue Storage Instance */
    private static $namespaces = [];

    /**
     * Get a queuable from domain configurations and queue name
     *
     * @param string $namespace: Namespace of domain class
     * @param string $name: Name of queue
     * @param string|null $driver: Name of queue driver
     * @param Queuable|null
     */
    public static function get(string $namespace, string $name, string $driver = null) : ?Queuable
    {
        $instance = self::$namespaces[$namespace] ?? null;
        if ($instance) {
            return $instance;
        }

        if (! $driver) {
            $driver = ConfigManager::getDomainFinalEnvByNamespace($namespace, self::QUEUE_DRIVER);
        }
        if (! $driver) {
            exception('MissingDomainQueueDriver', compact('namespace'));
        }
        $driver = strtolower($driver);
        $queuable = self::SUPPORT_DRIVERS[$driver] ?? null;
        if (! $queuable) {
            exception('UnSupportedQueueDriver', compact('namespace', 'driver'));
        }
        $config = ConfigManager::getDomainFinalByNamespace($namespace, $driver);
        if (! $config) {
            exception('MissingQueueDriverConfigurations', compact('namespace', 'driver'));
        }
        $pool = $config['pool'] ?? [];
        if (! $pool) {
            exception('MissingQueueConnectionPoolConfigurations', compact('namespace', 'driver', 'config'));
        }
        $queue = $config['queue'] ?? [];
        if (! $queue) {
            exception('MissingQueuableConnectionPoolConfigurations', compact('namespace', 'driver', 'config'));
        }

        $node = self::select($queue, $name);
        $conn = $queue[$node] ?? [];
        $node = $pool[$conn] ?? null;
        if (! $node) {
            exception('QueuableConnectionNotFoundInPool', compact(
                'namespace',
                'driver',
                'config',
                'conn'
            ));
        }

        $instance = new $queuable(self::buildAnnotationsByDriver($driver, $name, $node));
        $instance->setConnectionGetter(function () use ($driver, $conn, $node) {
            return Connection::get($driver, $conn, $node);
        });

        if (method_exists($instance, '__logging')) {
            Kernel::register('before-shutdown', function () use ($instance, $driver, $namespace) {
                try {
                    Kernel::appendContext("queue.{$driver}", $instance->__logging(), $namespace);
                } catch (Throwable $e) {
                    Log::log('exception', 'GetQueueStorageLoggingContextFailed', [
                            'namespace' => $namespace,
                            'message' => $e->getMessage(),
                        ]);
                }
            });
        }
        if (method_exists($instance, '__cleanup')) {
            Kernel::register('shutdown', function () use ($instance, $namespace) {
                try {
                    $instance->__cleanup();
                } catch (Throwable $e) {
                    Log::log('exception', 'CleanUpQueueStorageFailed', [
                            'namespace' => $namespace,
                            'message' => $e->getMessage(),
                        ]);
                }
            });
        }

        return self::$namespaces[$namespace] = $instance;
    }

    public static function buildAnnotationsByDriver(string $driver, string $key, array $config = [])
    {
        switch ($driver) {
            case 'redis':
                $dbNum = intval($config['dbnum'] ?? 16) - 1;
                $db = self::hash($key) % $dbNum;
                return ['meta' => ['DATABASE' => $db]];
            default:
                return [];
        }
    }

    public static function formatQueueName(string $queue, string $prefix = self::QUEUE_NORMAL) : string
    {
        return join(':', [$prefix, $queue]);
    }
}
