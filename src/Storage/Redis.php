<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Dof\Framework\Collection;
use Dof\Framework\TypeHint;
use Dof\Framework\Queue\Job;
use Dof\Framework\Queue\Queuable;

/**
 * API docs: <https://github.com/phpredis/phpredis/blob/develop/README.markdown>
 */
class Redis extends Storage implements Storable, Cachable, Queuable
{
    /** @var array: Commands executed in the lifetime of this instance */
    private $cmds = [];
    
    public function __call(string $method, array $params = [])
    {
        $start = microtime(true);

        $result = $this->getConnection()->{$method}(...$params);

        $this->appendCMD($start, $method, ...$params);

        return $result;
    }

    public function __logging()
    {
        return $this->cmds;
    }

    public function getConnection()
    {
        parent::getConnection();

        $db = $this->annotations->meta->DATABASE ?? null;
        if (is_null($db)) {
            exception('MissingDatabaseInRedisAnnotations', uncollect($this->annotations->meta ?? []));
        }
        if (! TypeHint::isUInt($db)) {
            exception('InvalidRedisDatabaseInAnnotations', [$db]);
        }

        $db = TypeHint::convertToUint($db);

        $start = microtime(true);
        $this->connection->select($db);
        $this->appendCMD($start, 'select', $db);

        return $this->connection;
    }

    public function getCacheValueTypeKey(string $key) : string
    {
        return "__CACHE_RAW_TYPE__:{$key}";
    }

    public function get(string $key)
    {
        $start = microtime(true);
        $result = $this->getConnection()->get($key);
        $this->appendCMD($start, 'get', $key);

        return $result === false ? null : unserialize($result);
    }

    public function dels(array $keys)
    {
        $start = microtime(true);
        $this->getConnection()->del($keys);
        $this->appendCMD($start, 'dels', $keys);
    }

    public function del(string $key)
    {
        $start = microtime(true);
        $this->getConnection()->del($key);
        $this->appendCMD($start, 'del', $key);
    }

    public function set(string $key, $value, int $expiration = 0)
    {
        $_value = serialize($value);

        $start = microtime(true);
        if ($expiration > 0) {
            $this->getConnection()->setEx($key, $expiration, $_value);
            $this->appendCMD($start, 'set', $key, $expiration, $_value);
        } else {
            $this->getConnection()->set($key, $_value);
            $this->appendCMD($start, 'set', $key, $_value);
        }
    }

    public function enqueue(string $queue, Job $job)
    {
        $_job = serialize($job);

        $start = microtime(true);

        $this->getConnection()->rPush($queue, $_job);

        $this->appendCMD($start, 'rPush', $queue, $_job);
    }

    public function dequeue(string $queue) :? Job
    {
        $start = microtime(true);

        $job = $this->getConnection()->rPop($queue);

        $this->appendCMD($start, 'rPop', $queue);

        return $job ? unserialize($job) : null;
    }

    public function setRestart(string $queue) : bool
    {
        $start = microtime(true);

        $res = $this->getConnection()->set($queue, $start);

        $this->appendCMD($start, 'set', $queue, $start);

        return $res === true;
    }

    public function restart(string $queue)
    {
        $start = microtime(true);

        $res = $this->getConnection()->del($queue);

        $this->appendCMD($start, 'del', $queue);

        return $res;
    }

    public function needRestart(string $queue) : bool
    {
        $start = microtime(true);

        $res = $this->getConnection()->get($queue);

        $this->appendCMD($start, 'get', $queue);

        return false !== $res;
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
