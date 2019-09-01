<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Event;

use Dof\Framework\Event;
use Dof\Framework\DDD\Entity;

class EntityUpdated extends Event
{
    const EVENT = 'ONUPDATED';

    protected $entity;
    protected $diff;

    /**
     * Setter for entity
     *
     * @param Entity $entity
     * @return EntityUpdated
     */
    public function setEntity(Entity $entity)
    {
        $this->entity = $entity;
    
        return $this;
    }

    /**
     * Setter for diff
     *
     * @param array $diff
     * @return EntityUpdated
     */
    public function setDiff(array $diff)
    {
        $this->diff = $diff;
    
        return $this;
    }
}
