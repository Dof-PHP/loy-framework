<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\Collection;

interface ORMRepository extends Repository
{
    /**
     * Add an entity to repository
     *
     * @param Entity $entity
     * @return int|null: Primary key when added succssflly or null on failed
     */
    public function add(Entity &$entity) : Entity;

    /**
     * Remove an entity from repository
     *
     * @param Entity|int: The entity instance or primary key of entity to remove
     * @return int|null: Number of rows affected or null on failed
     */
    public function remove($entity) : ?int;

    /**
     * Update an entity from repository
     *
     * @param Entity: The entity instance to be updated
     * @return Entity: The entity updated
     */
    public function save(Entity &$entity) : ?Entity;

    /**
     * Find entity by primary key
     *
     * @param int $pk: Primary key
     * @return Entity|null
     */
    public function find(int $pk) : ?Entity;

    /**
     * Get list of entities with pagination
     *
     * @param int $page
     * @param int $size
     * @param Collection $filter
     * @param string $sortField
     * @param string $sortOrder
     * @return array
     */
    public function list(
        int $page,
        int $size,
        Collection $filter,
        string $sortField = null,
        string $sortOrder = null
    );
}
