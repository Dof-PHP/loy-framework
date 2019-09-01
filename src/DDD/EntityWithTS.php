<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

/**
 * Entity with timestamps
 */
class EntityWithTS extends Entity
{
    /**
     * @Title(Created timestamp)
     * @Type(Uint)
     * @Notes(If no field parameters, then return timestamp as default)
     * @Argument(format){0=timestamp&1=Y-m-d H:i:s&2=y/m/d H:i:s&3=d/m/y H:i:s&default=0}
     */
    protected $createdAt;

    /**
     * @Title(Updated timestamp)
     * @Type(Uint)
     * @Notes(If no field parameters, then return timestamp as default)
     * @Default(0)
     * @NoDiff(1)
     * @Argument(format){0=timestamp&1=Y-m-d H:i:s&2=y/m/d H:i:s&3=d/m/y H:i:s&default=0}
     */
    protected $updatedAt;
}
