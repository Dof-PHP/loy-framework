<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Event;

use Dof\Framework\Event;
use Dof\Framework\DDD\Entity;

class EntityRemoved extends Event
{
    const EVENT = 'ONREMOVED';

    protected $entity;

    /**
     * Setter for entity
     *
     * @param Entity $entity
     * @return EntityRemoved
     */
    public function setEntity(Entity $entity)
    {
        $this->entity = $entity;
    
        return $this;
    }
}
