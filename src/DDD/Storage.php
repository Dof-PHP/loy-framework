<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Throwable;
use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;

/**
 * Storage is the persistence layer implementations
 * In Dof, it's also the configuration of ORM
 */
class Storage implements Repository
{
    /** @var Storage Instance */
    protected $__storage;

    public function __construct()
    {
        $this->__storage = StorageManager::init(static::class);
    }

    public function find(int $pk) : ?Entity
    {
        $result = $this->__storage->find($pk);

        return RepositoryManager::convert(static::class, $result);
    }

    public function add(Entity $entity) : ?int
    {
    }

    /**
     * Ignore when entity not exists in repository
     *
     * @param mixed $entity
     * @return bool
     */
    public function remove($entity) : bool
    {
        if ((! is_int($entity)) || (! ($entity instanceof Entity))) {
            return false;
        }

        if (is_int($entity)) {
            if ($entiry < 1) {
                return false;
            }
        }

        $pk = is_int($entity) ? $entity : $entity->getId();

        try {
            $this->__storage->delete($pk);
            return true;
        } catch (Throwable $e) {
            exception('RemoveEntityFailed', ['pk' => $pk, 'class' => static::class], $e);
        }
    }
}
