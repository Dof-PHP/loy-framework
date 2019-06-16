<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

interface Cachable
{
    public function get(string $key);

    public function set(string $key, $value, int $expiration = 0);

    public function del(string $key);
}
