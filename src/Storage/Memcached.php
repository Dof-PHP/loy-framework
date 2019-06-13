<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

class Memcached extends Storage implements StorageInterface
{
    private $cmds = [];

    public function __logging()
    {
        return $this->cmds;
    }

    public function __call(string $method, array $params = [])
    {
        $start = microtime(true);

        $result = $this->getConnection()->{$method}(...$params);

        $this->appendCMD($start, $method, ...$params);

        return $result;
    }

    private function appendCMD(float $start, string $cmd, ...$params)
    {
        $this->cmds[] = [
            microtime(true) - $start,
            $cmd,
            fixed_string(join(' ', $params), 255)
        ];

        return $this;
    }
}
