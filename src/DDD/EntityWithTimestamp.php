<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

class EntityWithTimestamp extends Entity
{
    /**
     * @Title(Created timestamp)
     * @Type(Uint)
     */
    protected $createdAt;

    /**
     * Getter for createdAt
     *
     * @return int
     */
    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }
    
    /**
     * Setter for createdAt
     *
     * @param int $createdAt
     * @return EntityWithTimestamp
     */
    public function setCreatedAt(int $createdAt)
    {
        $this->createdAt = $createdAt;
    
        return $this;
    }
}
