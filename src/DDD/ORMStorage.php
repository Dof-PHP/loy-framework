<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

class ORMStorage extends Storage
{
    /**
     * @Column(id)
     * @Type(int)
     * @Length(10)
     * @PrimaryKey(1)
     * @Notnull(1)
     */
    protected $id;
}
