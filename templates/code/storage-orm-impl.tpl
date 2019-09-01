<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Storage__NAMESPACE__;

// use Dof\Framework\Collection;
use Dof\Framework\DDD\__STORAGE__;
use Domain\__DOMAIN__\Repository\__NAME__Repository;

/**
 * @Repository(__NAME__Repository)
 * @Driver(mysql)
 * @Database(__DATABASE__)
 * @Table(__TABLE__)
 * @NoSync(0)
 * @_Cache(0)
 * @Comment(Comment of Table __NAME__)
 * @Engine(InnoDB)
 * @Charset(utf8mb4)
 * @_Index(idx_created_at){created_at}
 * @_Unique(uni_mobile){mobile}
 */
class __NAME__ORM extends __STORAGE__ implements __NAME__Repository
{
    /**
     * @Column(column_1)
     * @Type(varchar)
     * @Comment(Comment of column_1)
     * @Length(16)
     * @_UnSigned(1)
     * @_NotNull(0)
     * @Default()
     */
    protected $property1;
}
