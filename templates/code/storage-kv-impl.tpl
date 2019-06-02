<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Storage__NAMESPACE__;

use Dof\Framework\DDD\__STORAGE__;
use Domain\__DOMAIN__\Repository\__NAME__Repository;

/**
 * @Repository(__NAME__Repository)
 * @Driver(redis)
 * @Type(Hash)
 * @Database(15)
 * @Key(?)
 */
class __NAME__ extends __STORAGE__ implements __NAME__Repository
{
    /**
     * @Type(String)
     */
    protected $attr1;
}
