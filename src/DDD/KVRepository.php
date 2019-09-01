<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

interface KVRepository extends Repository
{
    /**
     * Get the type of kv storage
     */
    public static function type() : string;

    /**
     * Get the raw key definition of kv storage
     */
    public static function keyraw() : string;

    /**
     * Build the final key of kv storage
     *
     * @param array $params: The values used to convert parameters in the definition of raw key
     */
    public function key(...$params) : string;

    public function exists(...$params) : bool;
}
