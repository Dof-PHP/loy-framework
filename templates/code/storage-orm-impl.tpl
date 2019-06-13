<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Storage__NAMESPACE__;

use Dof\Framework\DDD\__STORAGE__;
use Domain\__DOMAIN__\Repository\__NAME__Repository;

/**
 * @Repository(__NAME__Repository)
 * @Driver(mysql)
 * @Database(__DOMAIN__)
 * @Table(__NAME__)
 * @Comment(Comment of Table __NAME__)
 * @Engine(InnoDB)
 * @Charset(utf8mb4)
 * @SoftDelete(0)
 */
class __NAME__ORM extends __STORAGE__ implements __NAME__Repository
{
    /**
     * @Column(column_1)
     * @Type(varchar)
     * @Comment(Comment Of table column)
     * @Length(16)
     * @Default()
     */
    protected $property1;
}
