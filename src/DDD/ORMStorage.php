<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;
use Dof\Framework\Paginator;
use Dof\Framework\Collection;

/**
 * In Dof, ORMStorage also the configuration of ORM
 */
class ORMStorage extends Storage
{
    /**
     * @Column(id)
     * @Type(int)
     * @Length(10)
     * @Unsigned(1)
     * @AutoInc(1)
     * @Notnull(1)
     */
    protected $id;

    final public function builder()
    {
        return $this->__storage->builder();
    }

    final public function paginate(int $page, int $size)
    {
        return $this->converts($this->builder()->paginate($page, $size));
    }

    final public static function table()
    {
        return self::annotations()['meta']['TABLE'] ?? null;
    }

    final public function add(Entity $entity) : Entity
    {
        $storage = static::class;
        $annotation = StorageManager::get($storage);
        $columns = $annotation['columns'] ?? [];
        if (! $columns) {
            exception('NoColumnsOnStorageToAdd', compact('storage'));
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

        // Add entity into repository cache
        RepositoryManager::add($storage, $entity);

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
        $storage = static::class;

        try {
            // Ignore when entity not exists in repository
            $res = $this->__storage->delete($pk);

            // Remove entity from repository cache
            RepositoryManager::remove($storage, $entity);

            return $res;
        } catch (Throwable $e) {
            exception('RemoveEntityFailed', compact('pk', 'storage'), $e);
        }
    }

    final public function save(Entity $entity) : Entity
    {
        $storage = static::class;
        $annotation = StorageManager::get($storage);
        $columns = $annotation['columns'] ?? [];
        if (! $columns) {
            exception('NoColumnsOnStorageToUpdate', ['storage' => static::class]);
        }

        unset($columns['id']);

        $data = [];
        foreach ($columns as $column => $property) {
            $getter = 'get'.ucfirst($property);
            $val = $entity->{$getter}() ?? null;
            // Null value check and set default if specific
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

        // Update/Reset repository cache
        RepositoryManager::update($storage, $entity);

        return $entity;
    }

    final public function find(int $pk) : ?Entity
    {
        // Find in repository cache first
        if ($entity = RepositoryManager::find(static::class, $pk)) {
            return $entity;
        }

        $result = $this->__storage->find($pk);
        if (! $result) {
            return null;
        }

        return RepositoryManager::map(static::class, $result);
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

    /**
     * Convert an array result data into entity object
     */
    final public function convert(array $result = null) : ?Entity
    {
        return RepositoryManager::map(static::class, $result);
    }

    /**
     * Convert a list of results or a paginator instance into entity object list
     */
    final public function converts($result = null)
    {
        if (! $result) {
            return;
        }

        if ($result instanceof Paginator) {
            $list = $result->getList();
            foreach ($list as &$item) {
                $item = RepositoryManager::map(static::class, $item);
            }
            $result->setList($list);

            return $result;
        }

        if (! is_array($result)) {
            exception('UnConvertableEntityOrigin', compact('result'));
        }

        foreach ($result as &$item) {
            $item = RepositoryManager::map(static::class, $item);
        }

        return $result;
    }
}
