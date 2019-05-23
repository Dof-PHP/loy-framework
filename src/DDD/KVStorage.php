<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

class KVStorage extends Storage
{
    final public function key(...$params) : string
    {
        $key = $this->storage()->annotations()->meta->KEY ?? null;

        if (is_null($key)) {
            exception('MissingKeyOfKVStorage', ['kv-storage' => static::class]);
        }

        return sprintf($key, ...$params);
    }
}
