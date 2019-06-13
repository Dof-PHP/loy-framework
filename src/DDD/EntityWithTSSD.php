<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

/**
 * Entity with timestamps and soft delete
 */
class EntityWithTSSD extends EntityWithTS
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
