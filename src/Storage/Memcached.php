<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

class Memcached extends Storage implements Storable, Cachable
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

    public function get(string $key)
    {
        $start = microtime(true);

        $result = $this->getConnection()->get($key);

        $this->appendCMD($start, 'get', $key);

        return $this->getConnection()->getResultCode() === \Memcached::RES_NOTFOUND
                ? null : $result;
    }

    public function del(string $key)
    {
        $start = microtime(true);

        $this->getConnection()->delete($key, 0);

        $this->appendCMD($start, 'del', $key);
    }

    public function dels(array $keys)
    {
        $start = microtime(true);

        $this->getConnection()->deleteMulti($keys, 0);

        $this->appendCMD($start, 'deleteMulti', $keys, 0);
    }

    public function set(string $key, $value, int $expiration = 0)
    {
        $start = microtime(true);

        $this->getConnection()->set($key, $value, $expiration);

        $this->appendCMD($start, 'set', $key, $value, $expiration);
    }
}
