<?php

declare(strict_types=1);

namespace Dof\Framework;

use Dof\Framework\Kernel;
use Dof\Framework\Storage\Connection;
use Dof\Framework\Storage\Cachable;
use Dof\Framework\Storage\Memcached;
use Dof\Framework\Storage\Redis;
use Dof\Framework\OFB\Traits\PartitionString;

final class CacheManager
{
    const CACHE_PREFIX = '__DOF_CACHE';

    use PartitionString;

    const SUPPORT_DRIVERS = [
        'memcached' => Memcached::class,
        'redis' => Redis::class,
    ];

    /** @var array: Domain class Namespace <=> Cache Storage Instance */
    private static $namespaces = [];

    /** @var array: Domain key <=> Cache Storage Instance */
    private static $domains = [];

    public static function getByDomain(string $domain, string $key, string $driver = null) : ?Cachable
    {
        $instance = self::$domains[$domain][$key] ?? null;
        if ($instance) {
            return $instance;
        }
    }

    /**
    * Get a cache driver instance of a domain, based on domain configurations
    *
    * @param string $namespace: Namespace of domain class
    * @param string $key: Cache key used to select a node for caching
    * @param string $driver: Cache driver name
    * @return Cachable|null
    */
    public static function get(string $namespace, string $key, string $driver = null) : ?Cachable
    {
        $instance = self::$namespaces[$namespace][$key] ?? null;
        if ($instance) {
            return $instance;
        }

        if (! $driver) {
            $driver = ConfigManager::getDomainFinalEnvByNamespace($namespace, 'CACHE_DRIVER');
        }
        if (! $driver) {
            exception('MissingDomainCacheStorageDriver', compact('namespace'));
        }

        $driver = strtolower($driver);
        $cachable = self::SUPPORT_DRIVERS[$driver] ?? null;
        if (! $cachable) {
            exception('UnSupportedCacheDriver', compact('driver', 'namespace'));
        }

        $config = ConfigManager::getDomainFinalByNamespace($namespace, $driver);
        if (! $config) {
            return null;
        }

        $pool = $config['pool'] ?? [];
        if (! $pool) {
            return null;
        }
        if ($cache = ($config['cache'] ?? [])) {
            $node = self::selectNodeFromOnly($cache, $key, $pool);
        } elseif ($group = ($config['group'] ?? [])) {
            $node = self::selectNodeFromGroup($group, $key, $pool);
        } else {
            $node = self::selectNodeFromPool($pool, $key);
        }

        if (! $node) {
            return null;
        }

        list($conn, $_config) = $node;

        $instance = new $cachable(self::buildAnnotationsByDriver($driver, $key, $_config));
        $instance->setConnectionGetter(function () use ($driver, $conn, $_config) {
            return Connection::get($driver, $conn, $_config);
        });

        if (method_exists($instance, '__logging')) {
            Kernel::register('before-shutdown', function () use ($instance, $driver, $namespace) {
                try {
                    Kernel::appendContext("cache.{$driver}", $instance->__logging(), $namespace);
                } catch (Throwable $e) {
                    Log::log('exception', 'GetCacheStorageLoggingContextFailed', [
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
                    Log::log('exception', 'CleanUpCacheStorageFailed', [
                            'namespace' => $namespace,
                            'message' => $e->getMessage(),
                        ]);
                }
            });
        }

        return self::$namespaces[$namespace][$key] = $instance;
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

    public static function selectNodeFromOnly(array $only, string $key, array $pool)
    {
        if (! $only) {
            return null;
        }

        $target = self::select($only, $key);

        $conn = $only[$target] ?? null;
        if (! $conn) {
            return null;
        }
        $config = $pool[$conn] ?? [];
        if (! $config) {
            return null;
        }

        return [$conn, $config];
    }

    public static function selectNodeFromGroup(array $group, string $key, array $pool)
    {
        $arr = array_trim_from_string($key, ':');
        $first = $arr[0] ?? null;
        if (! $first) {
            return null;
        }
        $_group = $group[$first] ?? null;
        if (! $_group) {
            $firsts = array_keys($group);
            $node = self::select($firsts, $key);
            $_group = $firsts[$node] ?? null;
        }
        if (! $_group) {
            return null;
        }

        return self::selectNodeFromOnly($_group, $key, $pool);
    }

    public static function selectNodeFromPool(array $pool, string $key)
    {
        return self::selectNodeFromOnly(array_keys($pool), $key, $pool);
    }
}
