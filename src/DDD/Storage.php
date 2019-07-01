<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Closure;
use Throwable;
use Dof\Framework\Paginator;
use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;

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

    final public function transaction(Closure $transaction)
    {
        $this->__storage->transaction($transaction);
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

    /**
     * Convert an array result data into entity/model object
     */
    final public function convert(array $result = null) : ?Model
    {
        return RepositoryManager::map(static::class, $result);
    }

    /**
     * Convert a list of results or a paginator instance into entity/model object list
     */
    final public function converts($result = null)
    {
        if (! $result) {
            return [];
        }

        $storage = static::class;
        if ($result instanceof Paginator) {
            $list = $result->getList();
            foreach ($list as &$item) {
                $item = RepositoryManager::map($storage, $item);
            }
            $result->setList($list);

            return $result;
        }

        if (! is_index_array($result)) {
            exception('UnConvertableStorageOrigin', compact('result', 'storage'));
        }

        foreach ($result as &$item) {
            $item = RepositoryManager::map($storage, $item);
        }

        return $result;
    }

    final public function collect(array $result = null) : ?Collection
    {
        return collect($result);
    }

    final public function collects(array $result = null) : ?array
    {
        if (is_null($result)) {
            return null;
        }

        foreach ($result as &$item) {
            $item = collect($item);
        }

        return $result;
    }
}
