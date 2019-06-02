<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

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
     * @Argument(format){default=timestamp}
     */
    protected $updatedAt;

    public function getCreatedAt() : int
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(int $createdAt)
    {
        $this->createdAt = $createdAt;
    
        return $this;
    }

    public function getUpdatedAt() : int
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(int $updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
