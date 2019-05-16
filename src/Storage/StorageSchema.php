<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Dof\Framework\StorageManager;

final class StorageSchema
{
    const SUPPORT_DRIVERS = [
        'mysql' => MySQLSchema::class,
    ];

    /**
     * Sync a storage orm schema to their driver from their annotations
     *
     * @param string $storage: Namespace of storage orm class
     */
    public static function sync(string $storage, bool $force = false)
    {
        if (! class_exists($storage)) {
            exception('StorageClassNotExists', compact('storage'));
        }

        $data = StorageManager::get($storage);

        $driver = $data['meta']['DRIVER'] ?? null;
        if (! $driver) {
            exception('StorageDriverNotSet', compact('storage'));
        }
        $driver  = strtolower($driver);
        $_driver = self::SUPPORT_DRIVERS[$driver] ?? false;
        if (! $_driver) {
            exception('UnSuppoertedStorageDriver', compact('storage', 'driver'));
        }

        return $_driver::sync($storage, $data, StorageManager::init($storage, false), $force);
    }
}
