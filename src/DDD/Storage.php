<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

use Loy\Framework\StorageManager;

/**
 * Storage is the persistence layer implementations
 * In Loy, it's also the configuration of ORM
 */
class Storage implements Repository
{
    /** @var Storage Instance */
    protected $__storage;

    public function __construct()
    {
        $this->__storage = StorageManager::get(static::class);
    }

    public function find(int $pk) : ?Entity
    {
        return $this->__storage->find($pk);
    }

    public function add(Entity $entity) : ?int
    {
    }

    public function remove($entity) : ?int
    {
    }
}
