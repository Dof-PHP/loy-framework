<?php

declare(strict_types=1);

namespace Dof\Framework;

use Throwable;
use Dof\Framework\Kernel;
use Dof\Framework\Storage\MySQL;
use Dof\Framework\Facade\Log;
use Dof\Framework\Facade\Annotation;
use Dof\Framework\DDD\Repository;

final class StorageManager
{
    const SUPPORT_DRIVERS = [
        'mysql' => MySQL::class,
    ];
    const CONN_DEFAULT = 'conn_default';
    const CONN_POOL    = 'conn_pool';
    const STORAGE_DIR  = 'Storage';

    private static $dirs = [];
    private static $orms = [];

    /** @var array: Namespace <=> Connection Key KV */
    private static $namespaces = [];

    /** @var array: Connection Key <=> Storage Instance KV */
    private static $connections = [];

    /**
     * Compile storages among domain directories with cache management
     *
     * @param array $dirs
     */
    public static function load(array $dirs)
    {
        $cache = Kernel::formatCacheFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$orms) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_STORAGE_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([self::$dirs, self::$orms], $cache);
        }
    }

    /**
     * Compile storages among domain directories
     *
     * @param array $dirs
     * @param bool $cache
     */
    public static function compile(array $dirs, bool $cache = false)
    {
        if (count($dirs) < 1) {
            return;
        }

        // Reset
        self::$dirs = [];
        self::$orms = [];

        array_map(function ($item) {
            $dir = ospath($item, self::STORAGE_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Exceptions may thrown but let invoker to catch for different scenarios
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, $ofProperties, ) = $annotations;
                self::assemble($ofClass, $ofProperties);
            }
        }, __CLASS__);

        if ($cache) {
            array2code([self::$dirs, self::$orms], Kernel::formatCacheFile(__CLASS__));
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCacheFile(__CLASS__);
        if (is_file($cache)) {
            unlink($cache);
        }
    }

    /**
     * Assemble Repository From Annotations
     */
    public static function assemble(array $ofClass, array $ofProperties)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if ($exists = (self::$orms[$namespace] ?? false)) {
            exception('DuplicateStorageClass', compact('exists'));
        }

        self::$orms[$namespace]['meta'] = $ofClass['doc'] ?? [];
        foreach ($ofProperties as $property => $attr) {
            $_column = $attr['doc'] ?? [];
            $column  = $_column['COLUMN'] ?? false;
            if (! $column) {
                continue;
            }
            self::$orms[$namespace]['columns'][$column]      = $property;
            self::$orms[$namespace]['properties'][$property] = $_column;
        }
    }

    public static function __annotationFilterRepository(string $repository) : string
    {
        if (! interface_exists($repository)) {
            exception('RepositoryNotExists', compact('repository'));
        }
        if (! is_subinterface_of($repository, Repository::class)) {
            exception('InvalidRepositoryInterface', compact('repository'));
        }

        return trim($repository);
    }

    /**
     * Initialize storage driver instance for storage class
     *
     * @param string $namespace: Namespace of storage class
     * @return Storage Instance
     */
    public static function init(string $namespace)
    {
        $instance = self::$connections[self::$namespaces[$namespace] ?? null] ?? null;
        if ($instance) {
            return $instance;
        }

        // Find storage driver from orm annotation (first) and domain database config (second)
        $domain = DomainManager::getKeyByNamespace($namespace);
        list($ofClass, $ofProperties, ) = Annotation::parseNamespace($namespace);
        $meta   = array_change_key_case($ofClass['doc'] ?? [], CASE_LOWER);
        $driver = $meta['storage'] ?? null;
        if (! $driver) {
            exception('MissingORMStorage', compact('namespace'));
        }
        $storage = self::SUPPORT_DRIVERS[$driver] ?? null;
        if (! $storage) {
            exception('StorageDriverNotSuppert', compact('driver'));
        }
        $connection = $meta['connection'] ?: ConfigManager::getDomainFinalDatabaseByKey($domain, self::CONN_DEFAULT);
        if (! $connection) {
            exception('MissingStorageConnnection', compact('domain'));
        }
        $pool   = ConfigManager::getDomainFinalDatabaseByKey($domain, self::CONN_POOL);
        $config = $pool[$connection] ?? [];
        if (! $config) {
            exception('StorageConnnectionNotFound', compact('connection', 'domain'));
        }

        self::$namespaces[$namespace] = $key = join(':', [$driver, $connection]);
        $instance = self::$connections[$key] ?? null;
        if ($instance && ($instance instanceof $storage)) {
            return $instance;
        }

        // Merge configurations from annotation and config file
        // And allow annotations replace file configs
        // So $meta must be the 2nd parameter of array_merge()
        $config   = array_merge($config, $meta);
        $instance = singleton($storage, $config);

        if (method_exists($instance, 'setQuery')) {
            $columns = [];
            foreach ($ofProperties as $property) {
                $column = $property['doc']['COLUMN'] ?? false;
                if (! $column) {
                    continue;
                }
                $columns[] = $column;
            }
            $meta['columns'] = $columns;
            $instance->setQuery($meta);
        }
        if (method_exists($instance, '__cleanup')) {
            Kernel::register('shutdown', function () use ($instance, $storage) {
                try {
                    $instance->__cleanup();
                } catch (Throwable $e) {
                    Log::log('exception', 'CleanUpStorageFailed', [
                        'storage' => $storage,
                        'message' => $e->getMessage(),
                    ]);
                }
            });
        }

        return self::$connections[$key] = $instance;
    }

    public static function get(string $namespace)
    {
        return self::$orms[$namespace] ?? null;
    }

    public static function getOrms()
    {
        return self::$orms;
    }
}
