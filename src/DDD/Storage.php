<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Throwable;
use Dof\Framework\StorageManager;

/**
 * Storage is the persistence layer implementations
 */
abstract class Storage implements Repository
{
    /** @var Storage Instance */
    protected $__storage;

    public function __construct()
    {
        $this->__storage = StorageManager::init(static::class);
    }

    final public static function new()
    {
        return new static;
    }

    final public static function init()
    {
        return new static;
    }

    final public function storage()
    {
        return $this->__storage;
    }

    final public static function database()
    {
        return self::annotations()['meta']['DATABASE'] ?? null;
    }

    final public static function repository()
    {
        return self::annotations()['meta']['REPOSITORY'] ?? null;
    }

    final public static function driver()
    {
        return self::annotations()['meta']['DRIVER'] ?? null;
    }

    final public static function annotations()
    {
        return StorageManager::get(static::class);
    }
}
