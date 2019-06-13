<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

/**
 * ORM Storage with timestamps
 */
class ORMStorageWithTS extends ORMStorage
{
    /**
     * @Column(created_at)
     * @Type(int)
     * @Length(10)
     * @Notnull(1)
     * @Unsigned(1)
     */
    protected $createdAt;

    /**
     * @Column(updated_at)
     * @Type(int)
     * @Length(10)
     * @Notnull(1)
     * @Unsigned(1)
     * @Default(0)
     */
    protected $updatedAt;
}
