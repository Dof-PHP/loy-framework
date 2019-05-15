<?php

declare(strict_types=1);

namespace Dof\Framework;

use Throwable;
use Dof\Framework\Kernel;
use Dof\Framework\Storage\MySQL;
use Dof\Framework\Storage\Redis;
use Dof\Framework\Storage\Connection;
use Dof\Framework\Facade\Log;
use Dof\Framework\Facade\Annotation;
use Dof\Framework\DDD\Repository;

final class StorageManager
{
    const SUPPORT_DRIVERS = [
        'mysql' => MySQL::class,
        'redis' => Redis::class,
    ];

    const CONN_DEFAULT = 'default';
    const CONN_POOL    = 'pool';
    const STORAGE_DIR  = 'Storage';

    private static $dirs = [];
    private static $orms = [];

    /** @var array: ORM Namespace <=> Storage Instance */
    private static $namespaces = [];

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
        if (! ($ofClass['doc']['DRIVER'] ?? false)) {
            exception('MissingStorageDriver', compact('namespace'));
        }
        // Require database annotation here coz Dof\Framework\Storage\Connection will keep the first db name
        // When first connect to driver server and if other storages have different db name
        // Then it will coz table not found kind of errors
        if (! ($ofClass['doc']['DATABASE'] ?? false)) {
            exception('MissingStorageDatabaseName', compact('namespace'));
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

    public static function __annotationFilterUnique(string $index, array $ext, string $storage) : array
    {
        return self::__annotationFilterIndex();
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
     * @return Storage Instance
     */
    public static function init(string $namespace)
    {
        $instance = self::$namespaces[$namespace] ?? null;
        if ($instance) {
            return $instance;
        }

        // Find storage driver from orm annotation (first) and domain database config (second)
        $domain = DomainManager::getKeyByNamespace($namespace);
        list($ofClass, $ofProperties, ) = Annotation::parseNamespace($namespace);
        $meta = array_change_key_case($ofClass['doc'] ?? [], CASE_LOWER);
        $driver = $meta['driver'] ?? null;
        if (! $driver) {
            exception('UnknownORMStorageDriver', compact('namespace'));
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
            exception('StorageConnnectionNotFound', compact('connection', 'domain', 'pool'));
        }

        // Merge configurations from annotation and config file
        // And allow annotations replace file configs
        // So $meta must be the 2nd parameter of array_merge()
        $config = array_merge($config, $meta);
        $instance = new $storage;
        $hook = method_exists($instance, 'callbackOnConnected')
        ? function ($config) use ($instance) {
            $instance->callbackOnConnected($config);
        }
        : null;

        $instance->setConnection(Connection::get($driver, $connection, $config, $hook));

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

        // This should be executed for every storage class
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

        return self::$namespaces[$namespace] = $instance;
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
