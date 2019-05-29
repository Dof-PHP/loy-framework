<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;

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

    final public function find(int $pk) : ?Entity
    {
        $result = $this->__storage->find($pk);

        return RepositoryManager::convert(static::class, $result);
    }
}
