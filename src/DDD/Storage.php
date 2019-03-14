<?php

declare(strict_types=1);

namespace Loy\Framework\DDD;

use Loy\Framework\StorageManager;

class Storage implements Repository
{
    /** @var Storage Instance */
    protected $__storage;

    public function __construct()
    {
        $this->__storage = StorageManager::get(static::class);
    }

    public function find(int $pk) : ?Entity
    {
        dd($this->__storage->find($pk));
    }
}
