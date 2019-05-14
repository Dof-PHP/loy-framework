<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\Paginator;

/**
 * The repository abstract persistence access, whatever storage it is. That is its purpose.
 * THe fact that you're using a db or xml files or an ORM doesn't matter.
 * The Repository allows the rest of the application to ignore persistence details.
 * This way, you can easily test the app via mocking or stubbing and you can change storages if it's needed.
 *
 * Repositories deal with Domain/Business objects (from the app point of view), an ORM handles db objects.
 * A business objects IS NOT a db object, first has behaviour, the second is a glorified DTO, it only holds data.
 */
interface Repository
{
    /**
     * Find entity by primary key
     *
     * @param int $pk: Primary key
     * @return Entity|null
     */
    public function find(int $pk) : ?Entity;

    /**
     * Add an entity to repository
     *
     * @param Entity $entity
     * @return int|null: Primary key when added succssflly or null on failed
     */
    public function add(Entity $entity) : Entity;

    /**
     * Remove an entity from repository
     *
     * @param Entity|int: The entity instance or primary key of entity to remove
     * @return int|null: Number of rows affected or null on failed
     */
    public function remove($entity) : ?int;

    /**
     * Get pagination list of current entities
     *
     * @param int $page
     * @param int $size
     * @return array
     */
    public function paginate(int $page, int $size) : Paginator;
}
