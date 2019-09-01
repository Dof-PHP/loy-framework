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
     * @Argument(format){0=timestamp&1=Y-m-d H:i:s&2=y/m/d H:i:s&3=d/m/y H:i:s&default=0}
     */
    protected $deletedAt;

    /**
     * @Title(Soft-deleted status)
     * @Type(Bint)
     * @Default(0)
     */
    protected $isDeleted;
}
