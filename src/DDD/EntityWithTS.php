<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

class EntityWithTS extends Entity
{
    /**
     * @Title(Created timestamp)
     * @Type(Uint)
     */
    protected $createdAt;

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(int $createdAt)
    {
        $this->createdAt = $createdAt;
    
        return $this;
    }
}
