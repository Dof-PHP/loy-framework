<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

class KVStorage extends Storage
{
    final public static function type() : string
    {
        return self::annotations()['meta']['TYPE'] ?? null;
    }

    final public static function keyraw() : string
    {
        return self::annotations()['meta']['KEY'] ?? null;
    }

    final public function key(...$params) : string
    {
        $key = $this->storage()->annotations()->meta->KEY ?? null;

        if (is_null($key)) {
            exception('MissingKeyOfKVStorage', ['kv-storage' => static::class]);
        }

        return $params ? sprintf($key, ...$params) : $key;
    }

    public function exists(...$params) : bool
    {
        if (method_exists($this, '__exists')) {
            return (bool) $this->__exists(...$params);
        }

        $key = $this->key(...$params);

        return $this->storage()->exists($key) > 0;
    }
}
