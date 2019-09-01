<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Closure;
use Throwable;
use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;
use Dof\Framework\Collection;
use Dof\Framework\TypeHint;
use Dof\Framework\OFB\Event\EntityCreated;
use Dof\Framework\OFB\Event\EntityRemoved;
use Dof\Framework\OFB\Event\EntityUpdated;

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

    final public function count() : int
    {
        return $this->builder()->count();
    }

    final public function paginate(int $page, int $size)
    {
        return $this->converts($this->builder()->paginate($page, $size));
    }

    final public static function table(bool $database = false, bool $prefix = true) : string
    {
        $meta = self::annotations()['meta'] ?? [];
        $table = $meta['TABLE'] ?? '';
        if ($prefix) {
            $prefix = $meta['PREFIX'] ?? '';
        }
        if ($database && ($database = $meta['DATABASE'] ?? '')) {
            $database = "`{$database}`.";
        }

        return "{$database}`{$prefix}{$table}`";
    }

    final public function create(Entity &$entity) : Entity
    {
        return $this->add($entity);
    }

    final public function add(Entity &$entity) : Entity
    {
        $storage = static::class;
        $annotation = StorageManager::get($storage);
        $columns = $annotation['columns'] ?? [];
        if (! $columns) {
            exception('NoColumnsOnStorageToAdd', compact('storage'));
        }

        if (property_exists($entity, Entity::CREATED_AT) && is_null($entity->{Entity::CREATED_AT})) {
            $entity->{Entity::CREATED_AT} = time();
        }
        if (property_exists($entity, Entity::UPDATED_AT)) {
            $entity->{Entity::UPDATED_AT} = 0;
        }

        unset($columns['id']);

        $data = [];
        foreach ($columns as $column => $property) {
            $attribute = $annotation['properties'][$property] ?? [];
            // Ignore insert with column if property not exists in entity
            if (! property_exists($entity, $property)) {
                continue;
            }
            $val = $entity->{$property} ?? null;
            // Null value check and set default if necessary
            if (is_null($val)) {
                $val = $attribute['DEFAULT'] ?? null;
            }

            $type = $attribute['TYPE'] ?? null;
            if (! $type) {
                $entity = get_class($entity);
                exception('MissingEntityType', compact('type', 'attribute', 'storage', 'entity'));
            }
            if (! TypeHint::support($type)) {
                $entity = get_class($entity);
                exception('UnsupportedEntityType', compact('type', 'attribute', 'storage', 'entity'));
            }

            $data[$column] = TypeHint::convert($val, $type, true);
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

        if (method_exists($entity, Entity::ON_CREATED)) {
            $entity->{Entity::ON_CREATED}();
        } elseif ($entity->__getOnCreated()) {
            EntityCreated::new()->setEntity($entity)->publish();
        }

        return $entity;
    }

    final public function deletes(array $pks)
    {
        $this->removes($pks);
    }

    final public function removes(array $pks)
    {
        $pks = array_unique($pks);
        foreach ($pks as $pk) {
            if (! TypeHint::isPint($pk)) {
                continue;
            }
            $pk = TypeHint::convertToPint($pk);
            $this->remove($pk);
        }
    }

    final public function delete($entity) : ?int
    {
        return $this->remove($entity);
    }

    final public function remove($entity) : ?int
    {
        if ((! is_int($entity)) && (! ($entity instanceof Entity))) {
            return 0;
        }
        if (is_int($entity)) {
            if ($entity < 1) {
                return 0;
            }

            $entity = $this->find($entity);
            if (! $entity) {
                return 0;
            }
        }

        $pk = $entity->getPk();
        $storage = static::class;

        try {
            // Ignore when entity not exists in repository
            $result = $this->__storage->delete($pk);
            // Remove entity from repository cache
            RepositoryManager::remove($storage, $entity);

            if ($result > 0) {
                if (method_exists($entity, Entity::ON_REMOVED)) {
                    $entity->{Entity::ON_REMOVED}();
                } elseif ($entity->__getOnRemoved()) {
                    EntityRemoved::new()->setEntity($entity)->publish();
                }
            }

            return $result;
        } catch (Throwable $e) {
            $entity = get_class($entity);
            exception('RemoveEntityFailed', compact('entity', 'pk', 'storage'), $e);
        }
    }

    final public function save(Entity &$entity, &$updated = false) : ?Entity
    {
        $_pk = $entity->getPk();
        if ((! is_int($_pk)) || ($_pk < 1)) {
            return null;
        }
        $_entity = $this->find($_pk);
        if (! $_entity) {
            return null;
        }

        $diff = $entity->compare($_entity);
        if (! $diff) {
            return $entity;
        }

        $storage = static::class;
        $annotation = StorageManager::get($storage);
        $columns = $annotation['columns'] ?? [];

        if (! $columns) {
            exception('NoColumnsOnStorageToUpdate', ['storage' => static::class]);
        }

        if (property_exists($entity, Entity::UPDATED_AT)) {
            $entity->{Entity::UPDATED_AT} = time();
            $diff[Entity::UPDATED_AT] = [];
        }

        // Primary key is not allowed to update
        unset($columns['id']);

        $data = [];
        foreach ($columns as $column => $property) {
            if (! is_array($diff[$property] ?? null)) {
                continue;
            }
            if (property_exists($entity, Entity::CREATED_AT) && ($property === Entity::CREATED_AT)) {
                continue;
            }

            $attribute = $annotation['properties'][$property] ?? [];
            $val = $entity->{$property} ?? null;
            // Null value check and set default if specific
            if (is_null($val)) {
                $val = $attribute['DEFAULT'] ?? null;
            }

            $type = $attribute['TYPE'] ?? null;
            if (! $type) {
                $entity = get_class($entity);
                exception('MissingEntityType', compact('type', 'attribute', 'storage', 'entity'));
            }
            if (! TypeHint::support($type)) {
                $entity = get_class($entity);
                exception('UnsupportedEntityType', compact('type', 'attribute', 'storage', 'entity'));
            }

            $data[$column] = TypeHint::convert($val, $type, true);
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

        if (method_exists($entity, Entity::ON_UPDATED)) {
            $entity->{Entity::ON_UPDATED}($_entity);
        } elseif ($entity->__getOnUpdated()) {
            unset($diff[Entity::UPDATED_AT]);
            EntityUpdated::new()
                ->setEntity($entity)
                ->setDiff($diff)
                ->publish();
        }

        $updated = true;

        return $entity;
    }

    final public function finds(array $pks)
    {
        $list = [];

        $pks = array_unique($pks);
        foreach ($pks as $pk) {
            if (! TypeHint::isPint($pk)) {
                continue;
            }
            $pk = TypeHint::convertToPint($pk);
            if ($entity = $this->find($pk)) {
                $list[] = $entity;
            }
        }

        return $list;
    }

    final public function find(int $pk) : ?Entity
    {
        $class = static::class;
        // Find in repository cache first
        if ($entity = RepositoryManager::find($class, $pk)) {
            return $entity;
        }

        $result = $this->__storage->find($pk);
        if (! $result) {
            return null;
        }

        $entity = RepositoryManager::map($class, $result);
        RepositoryManager::add(static::class, $entity);

        return $entity;
    }

    final public function filter(
        Closure $filter,
        int $page,
        int $size,
        string $sortField = null,
        string $sortOrder = null
    ) {
        $builder = $this->sorter($sortField, $sortOrder);

        $result = $filter($builder);
        if (is_null($result)) {
            return $this->converts($builder->paginate($page, $size));
        }

        return $result;
    }

    final public function sorter(string $sortField = null, string $sortOrder = null)
    {
        $builder = $this->builder();

        if ($sortField && ($column = $this->column($sortField))) {
            $builder->order($column, $sortOrder ?: 'desc');
        } else {
            $builder->order('id', 'desc');
        }

        return $builder;
    }

    public function list(
        int $page,
        int $size,
        Collection $filter,
        string $sortField = null,
        string $sortOrder = null
    ) {
        // TO-BE-OVERWRITE
        return $this->converts($this->sorter($sortField, $sortOrder)->paginate($page, $size));
    }

    final public function column(string $attr) : ?string
    {
        return $this->annotations()['properties'][$attr]['COLUMN'] ?? null;
    }

    /**
     * Set a value for a entity attr without guaranteeing record existence
     */
    final public function set(int $pk, string $attr, $value)
    {
        $column = $this->column($attr);
        if (! $column) {
            exception('MissingColumnOfAttributeToSet', compact('attr'));
        }

        $result = $this->builder()->where('id', $pk)->set($column, $value);

        if ($result > 0) {
            RepositoryManager::remove(static::class, $pk);
        }

        return $this;
    }

    /**
     * Set a batch value for entity attrs without guaranteeing record existence
     */
    final public function update(int $pk, array $data)
    {
        $_data = [];
        foreach ($data as $attr => $value) {
            $column = $this->column($attr);
            if (! $column) {
                exception('MissingColumnOfAttributeToSet', compact('attr'));
            }
            $_data[$column] = $value;
        }

        if (! $_data) {
            return $this;
        }

        $result = $this->builder()->where('id', $pk)->update($_data);

        if ($result > 0) {
            RepositoryManager::remove(static::class, $pk);
        }

        return $this;
    }

    /**
     * Flush entity cache only - single
     *
     * @param int $pk
     */
    final public function flush(int $pk)
    {
        RepositoryManager::remove(static::class, $pk);
    }

    /**
     * Flush entity cache only - multiples
     *
     * @param array $pks
     */
    final public function flushs(array $pks)
    {
        RepositoryManager::removes(static::class, $pks);
    }
}
