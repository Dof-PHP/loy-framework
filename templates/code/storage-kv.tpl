<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Storage__NAMESPACE__;

use Dof\Framework\DDD\KVStorage;

// use Domain\__DOMAIN__\Repository\__NAME__Repository;

/**
 * @_Repository(__NAME__Repository)
 * @Driver(redis)
 * @Type(Hash)
 * @Database(15)
 * @Key(__NAME__:%s)
 */
// class __NAME__ extends KVStorage implements __NAME__Repository
class __NAME__ extends KVStorage
{
    /**
     * @Type(String)
     */
    protected $attr1;
}
