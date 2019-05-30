<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Storage__NAMESPACE__;

use Dof\Framework\DDD\__PARENT__;
use Domain\__DOMAIN__\Repository\__NAME__Repository;

/**
 * @Repository(__NAME__Repository)
 * @Driver(mysql)
 * @Database(?)
 * @Table(__NAME__)
 * @Comment(Table of __NAME__)
 * @Engine(InnoDB)
 * @Charset(utf8mb4)
 * @SoftDelete(1)
 */
class __NAME__ORM extends __PARENT__ implements __NAME__Repository
{
    /**
     * @Column(column_1)
     * @Type(varchar)
     * @Comment(?)
     * @Length(16)
     * @Default()
     */
    protected $property1;
}