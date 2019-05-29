<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

abstract class Entity extends Model
{
    /**
     * @Title(Entity Identity)
     * @Type(Uint)
     */
    protected $id;

    final public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    final public function getId()
    {
        return $this->id;
    }
}
