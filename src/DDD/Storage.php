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
     * Convert an array result data into entity object
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

    final public function add(Entity $entity) : Entity
    {
        $annotation = StorageManager::get(static::class);
        $columns = $annotation['columns'] ?? [];
        if (! $columns) {
            exception('NoColumnsOnStorageToAdd', ['storage' => static::class]);
        }

        unset($columns['id']);

        $data = [];
        foreach ($columns as $column => $property) {
            $getter = 'get'.ucfirst($property);
            $val = $entity->{$getter}() ?? null;
            // Null value check and set default if necessary
            if (is_null($val)) {
                $_property = $annotation['properties'][$property] ?? null;
                $val = $_property['DEFAULT'] ?? null;
            }

            $data[$column] = $val;
        }

        if (! $data) {
            exception('NoDataForStorageToAdd', [
                'storage' => static::class,
                'entity'  => get_class($entity),
            ]);
        }

        $id = $this->__storage->add($data);

        $entity->setId($id);

        return $entity;
    }

    final public function remove($entity) : ?int
    {
        if ((! is_int($entity)) && (! ($entity instanceof Entity))) {
            return false;
        }

        if (is_int($entity)) {
            if ($entity < 1) {
                return false;
            }
        }

        $pk = is_int($entity) ? $entity : $entity->getId();

        try {
            // Ignore when entity not exists in repository
            return $this->__storage->delete($pk);

            // TODO: Flush repository cache
        } catch (Throwable $e) {
            exception('RemoveEntityFailed', ['pk' => $pk, 'class' => static::class], $e);
        }
    }

    final public function save(Entity $entity) : Entity
    {
        $annotation = StorageManager::get(static::class);
        $columns = $annotation['columns'] ?? [];
        if (! $columns) {
            exception('NoColumnsOnStorageToUpdate', ['storage' => static::class]);
        }

        unset($columns['id']);

        $data = [];
        foreach ($columns as $column => $property) {
            $getter = 'get'.ucfirst($property);
            $val = $entity->{$getter}() ?? null;
            if (is_null($val)) {
                $_property = $annotation['properties'][$property] ?? null;
                $val = $_property['DEFAULT'] ?? null;
            }

            $data[$column] = $val;
        }

        if (! $data) {
            exception('NoDataForStorageToUpdate', [
                'storage' => static::class,
                'entity'  => get_class($entity),
            ]);
        }

        $this->__storage->update($entity->getId(), $data);

        return $entity;
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

    final public function find(int $pk) : ?Entity
    {
        $result = $this->__storage->find($pk);

        return RepositoryManager::convert(static::class, $result);
    }
}
