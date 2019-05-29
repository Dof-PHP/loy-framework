<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Throwable;
use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;
use Dof\Framework\Paginator;

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

    /**
     * Convert an array result data into object of entity or data model
     */
    final public function convert(array $result = null)
    {
        return RepositoryManager::convert(static::class, $result);
    }

    /**
     * Convert a list of results or a paginator instance
     */
    final public function converts($result = null)
    {
        if (! $result) {
            return;
        }

        if ($result instanceof Paginator) {
            $list = $result->getList();
            foreach ($list as &$item) {
                $item = RepositoryManager::convert(static::class, $item);
            }
            $result->setList($list);

            return $result;
        }

        if (! is_array($result)) {
            exception('UnconvertableEntityOrigin', compact('result'));
        }

        foreach ($result as &$item) {
            $item = RepositoryManager::convert(static::class, $item);
        }

        return $result;
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
