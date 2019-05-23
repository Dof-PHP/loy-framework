<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

/**
 * In Dof, ORMStorage also the configuration of ORM
 */
class ORMStorage extends Storage
{
    /**
     * @Column(id)
     * @Type(int)
     * @Length(10)
     * @Unsigned(1)
     * @AutoInc(1)
     * @Notnull(1)
     */
    protected $id;

    final public function builder()
    {
        return $this->__storage->builder();
    }

    final public function paginate(int $page, int $size)
    {
        return $this->converts($this->builder()->paginate($page, $size));
    }
}
