<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

class Entity
{
    protected $id;

    /**
     * Setter for id
     *
     * @param int $id
     * @return Entity
     */
    public function setId(int $id)
    {
        $this->id = $id;
    
        return $this;
    }

    /**
     * Getter for id
     *
     * @return int|null
     */
    public function getId() : ?int
    {
        return $this->id;
    }
}
