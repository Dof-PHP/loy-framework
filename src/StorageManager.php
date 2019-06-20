<?php

declare(strict_types=1);

namespace Dof\Framework;

use Throwable;
use Dof\Framework\Kernel;
use Dof\Framework\Storage\MySQL;
use Dof\Framework\Storage\Redis;
use Dof\Framework\Storage\Memcached;
use Dof\Framework\Storage\Connection;
use Dof\Framework\Facade\Annotation;
use Dof\Framework\Facade\Log;
use Dof\Framework\DDD\Repository;

final class StorageManager
{
    const SUPPORT_DRIVERS = [
        'mysql' => MySQL::class,
        'redis' => Redis::class,
        'memcached' => Memcached::class,
    ];

    const CONN_DEFAULT = 'default';
    const CONN_POOL    = 'pool';
    const STORAGE_DIR  = 'Storage';

    private static $dirs = [];
    private static $storages = [];

    /** @var array: Storage Namespace <=> Storage Instance */
    private static $namespaces = [];

    /**
     * Compile storages among domain directories with cache management
     *
     * @param array $dirs
     */
    public static function load(array $dirs)
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
        if (is_file($cache)) {
            list(self::$dirs, self::$storages) = load_php($cache);
            return;
        }

        self::compile($dirs);

        if (ConfigManager::matchEnv(['ENABLE_STORAGE_CACHE', 'ENABLE_MANAGER_CACHE'], false)) {
            array2code([self::$dirs, self::$storages], $cache);
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
        // Reset
        self::$dirs = [];
        self::$storages = [];

        if (count($dirs) < 1) {
            return;
        }

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
            array2code([self::$dirs, self::$storages], Kernel::formatCompileFile(__CLASS__));
        }
    }

    public static function flush()
    {
        $cache = Kernel::formatCompileFile(__CLASS__);
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
        if ($exists = (self::$storages[$namespace] ?? false)) {
            exception('DuplicateStorageClass', compact('exists'));
        }
        $driver = $ofClass['doc']['DRIVER'] ?? null;
        if (! $driver) {
            exception('MissingStorageDriver', compact('namespace'));
        }
        if (! (self::SUPPORT_DRIVERS[$driver] ?? null)) {
            exception('StorageDriverNotSupport', compact('driver'));
        }
        // Require database annotation here coz Dof\Framework\Storage\Connection will keep the first db name
        // When first connect to driver server and if other storages have different db name
        // Then it will coz table not found kind of errors
        if ((! ci_equal($driver, 'memcached')) && (! ($ofClass['doc']['DATABASE'] ?? false))) {
            exception('MissingStorageDatabaseName', compact('namespace'));
        }

        self::$storages[$namespace]['meta'] = $ofClass['doc'] ?? [];
        self::$storages[$namespace]['meta']['NAMESPACE'] = $namespace;
        foreach ($ofProperties as $property => $attr) {
            $_column = $attr['doc'] ?? [];
            $column  = $_column['COLUMN'] ?? false;
            if (! $column) {
                continue;
            }
            self::$storages[$namespace]['columns'][$column] = $property;
            self::$storages[$namespace]['properties'][$property] = $_column;
        }
    }

    public static function __annotationFilterUnique(string $index, array $ext, string $storage) : array
    {
        return self::__annotationFilterIndex($index, $ext, $storage);
    }

    public static function __annotationFilterIndex(string $index, array $ext, string $storage) : array
    {
        $fields = array_keys($ext)[0] ?? '';
        $fields = array_trim_from_string($fields, ',');
        if (count($fields) < 1) {
            exception('MissingIndexFields', compact('index'));
        }

        return [trim($index) => $fields];
    }

    public static function __annotationMultipleMergeIndex()
    {
        return 'assoc';
    }

    public static function __annotationMultipleMergeUnique()
    {
        return 'assoc';
    }

    public static function __annotationMultipleUnique() : bool
    {
        return true;
    }

    public static function __annotationMultipleIndex() : bool
    {
        return true;
    }

    public static function __annotationFilterRepository(string $repository, array $ext, string $storage) : string
    {
        $repository  = trim($repository);
        $_repository = get_annotation_ns($repository, $storage);

        if ((! $_repository) || (! interface_exists($_repository))) {
            exception('MissingOrRepositoryNotExists', compact('repository', 'storage'));
        }

        if (! is_subinterface_of($_repository, Repository::class)) {
            exception('InvalidRepositoryInterface', compact('repository', 'storage'));
        }

        return $_repository;
    }

    /**
     * Initialize storage driver instance for storage class
     *
     * @param string $namespace: Namespace of storage class
     * @return \Dof\Framework\Storage\Storable
     */
    public static function init(string $namespace)
    {
        $instance = self::$namespaces[$namespace] ?? null;
        if ($instance) {
            return $instance;
        }

        // Find storage driver from annotations (first) and domain database config (second)
        $domain = DomainManager::getKeyByNamespace($namespace);
        if (! $domain) {
            exception('BadStorageWithOutDomain');
        }

        $annotations = self::get($namespace);
        $meta = array_change_key_case($annotations['meta'] ?? [], CASE_LOWER);
        $driver = $meta['driver'] ?? null;
        if (! $driver) {
            exception('UnknownStorageDriver', compact('namespace'));
        }
        $storage = self::SUPPORT_DRIVERS[$driver] ?? null;
        if (! $storage) {
            exception('StorageDriverNotSupport', compact('driver'));
        }
        $connection = $meta['connection'] ?? ConfigManager::getDomainFinalByKey($domain, join('.', [$driver, self::CONN_DEFAULT]));
        if (! $connection) {
            exception('MissingStorageConnnection', compact('domain'));
        }
        $pool = ConfigManager::getDomainFinalByKey($domain, join('.', [$driver, self::CONN_POOL]));
        $config = $pool[$connection] ?? [];
        if (! $config) {
            exception('StorageConnnectionConfigMissing', compact('connection', 'domain', 'pool'));
        }
        $config = array_change_key_case($config, CASE_LOWER);

        // Merge configurations from annotation and config file
        // And allow annotations replace file configs
        // So $meta must be the 2nd parameter of array_merge()
        $config = array_merge($config, $meta);
        $instance = new $storage($annotations);
        unset($config['database'], $meta['database']);

        // Avoid an actual connection to db driver when we are using cache
        $instance->setConnectionGetter(function () use ($driver, $connection, $config) {
            return Connection::get($driver, $connection, $config);
        });

        if (method_exists($instance, '__logging')) {
            Kernel::register('before-shutdown', function () use ($instance, $storage, $driver, $namespace) {
                try {
                    Kernel::appendContext($driver, $instance->__logging(), $namespace);
                } catch (Throwable $e) {
                    Log::log('exception', 'GetStorageLoggingContextFailed', [
                            'storage' => $storage,
                            'message' => $e->getMessage(),
                        ]);
                }
            });
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

        return self::$namespaces[$namespace] = $instance;
    }

    public static function get(string $namespace)
    {
        return self::$storages[$namespace] ?? null;
    }

    public static function getStorages()
    {
        return self::$storages;
    }
}
