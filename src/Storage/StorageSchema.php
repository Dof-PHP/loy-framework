<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Throwable;
use Dof\Framework\StorageManager;

final class StorageSchema
{
    const SUPPORT_DRIVERS = [
        'mysql' => MySQLSchema::class,
    ];

    public static function prepare(string $storage)
    {
        if (! class_exists($storage)) {
            exception('StorageClassNotExists', compact('storage'));
        }

        $annotations = StorageManager::get($storage);

        $driver = $annotations['meta']['DRIVER'] ?? null;
        if (! $driver) {
            exception('StorageDriverNotSet', compact('storage'));
        }
        $driver  = strtolower($driver);
        $_driver = self::SUPPORT_DRIVERS[$driver] ?? false;
        if (! $_driver) {
            exception('UnSuppoertedStorageDriver', compact('storage', 'driver'));
        }

        return [$_driver, $annotations];
    }

    /**
     * Sync a storage orm schema to their driver from their annotations
     *
     * @param string $storage: Namespace of storage orm class
     */
    public static function sync(string $storage, bool $force = false, bool $dump = false)
    {
        list($driver, $annotations) = self::prepare($storage);

        try {
            return singleton($driver)->reset()
                ->setStorage($storage)
                ->setAnnotations($annotations)
                ->setDriver(StorageManager::init($storage, false))
                ->setForce($force)
                ->setDump($dump)
                ->sync();
        } catch (Throwable $e) {
            exception('SyncORMStorageException', compact('storage', 'force', 'dump'), $e);
        }
    }

    public static function init(string $storage, bool $force = false, bool $dump = false)
    {
        list($driver, $annotations) = self::prepare($storage);

        try {
            return singleton($driver)->reset()
                ->setStorage($storage)
                ->setAnnotations($annotations)
                ->setDriver(StorageManager::init($storage, false))
                ->setForce($force)
                ->setDump($dump)
                ->init();
        } catch (Throwable $e) {
            exception('InitORMStorageException', compact('storage', 'force', 'dump'), $e);
        }
    }
}
