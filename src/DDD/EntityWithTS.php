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
     * @Argument(format){default=timestamp}
     */
    protected $createdAt;

    /**
     * @Title(Updated timestamp)
     * @Type(Uint)
     * @Notes(If no field parameters, then return timestamp as default)
     * @Default(0)
     * @NoDiff(1)
     * @Argument(format){default=timestamp}
     */
    protected $updatedAt;
}
