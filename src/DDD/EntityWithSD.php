<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

/**
 * Entity with soft delete
 */
class EntityWithSD extends Entity
{
    /**
     * @Title(Deleted timestamp)
     * @Type(Uint)
     * @Notes(If no field parameters, then return timestamp as default)
     * @Argument(format){default=timestamp}
     */
    protected $deletedAt;

    /**
     * @Title(Deleted status)
     * @Type(Bint)
     * @Default(0)
     */
    protected $isDeleted;
}
