<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Event;

use Dof\Framework\Event;
use Dof\Framework\DDD\Entity;

class EntityCreated extends Event
{
    const EVENT = 'ONCREATED';

    protected $entity;

    /**
     * Setter for entity
     *
     * @param Entity $entity
     * @return EntityCreated
     */
    public function setEntity(Entity $entity)
    {
        $this->entity = $entity;
    
        return $this;
    }
}
