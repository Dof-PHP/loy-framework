<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Dof\Framework\Collection;
use Dof\Framework\TypeHint;

/**
 * API docs: <https://github.com/phpredis/phpredis/blob/develop/README.markdown>
 */
class Redis extends Storage implements Storable, Cachable
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
        if (! $db) {
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

    private function appendCMD(float $start, string $cmd, ...$params)
    {
        $this->cmds[] = [
            microtime(true) - $start,
            $cmd,
            fixed_string(join(' ', $params), 255)
        ];

        return $this;
    }

    public function getCacheValueTypeKey(string $key) : string
    {
        return "__CACHE_RAW_TYPE__:{$key}";
    }

    public function get(string $key)
    {
        $connection = $this->getConnection();

        // $start = microtime(true);
        // $_key = $this->getCacheValueTypeKey($key);
        // $type = $connection->get($_key);
        // $this->appendCMD($start, 'get', $_key);
        // if (false === $type) {
        // $type = 'string';
        // }

        $start = microtime(true);
        $result = $connection->get($key);
        $this->appendCMD($start, 'get', $key);

        return $result === false ? null : unserialize($result);
    }

    public function del(string $key)
    {
        $start = microtime(true);
        $this->getConnection()->del($key);
        $this->appendCMD($start, 'del', $key);
    }

    public function set(string $key, $value, int $expiration = 0)
    {
        // $_key = $this->getCacheValueTypeKey($key);
        $_value = $this->stringify($value);

        $start = microtime(true);
        if ($expiration > 0) {
            $this->getConnection()->setEx($key, $expiration, $_value);
            $this->appendCMD($start, 'set', $key, $expiration, fixed_string($_value, 255));
        } else {
            $this->getConnection()->set($key, $_value);
            $this->appendCMD($start, 'set', $key, fixed_string($_value, 255));
        }
    }

    public function typehint(string $result, string $type)
    {
        $type = strtolower($type);

        switch ($type) {
            case 'object':
                return unserialize($result);
            // TODO
            case 'string':
            default:
                return $result;
        }
    }

    public function stringify($result) : string
    {
        return serialize($result);
    }
}
