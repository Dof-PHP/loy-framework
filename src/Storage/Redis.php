<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Closure;
use Throwable;
use Dof\Framework\Collection;
use Dof\Framework\TypeHint;

/**
 * API docs: <https://github.com/phpredis/phpredis/blob/develop/README.markdown>
 */
class Redis implements StorageInterface
{
    private $annotations;

    private $connection;

    /** @var Closure: The getter to get acutal connection */
    private $connectionGetter;

    /** @var array: Commands executed in the lifetime of this instance */
    private $cmds = [];
    
    public function __construct(array $annotations = [])
    {
        $this->annotations = collect($annotations);
    }

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

    public function setConnectionGetter(Closure $getter)
    {
        $this->connectionGetter = $getter;

        return $this;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection()
    {
        if ((! $this->connection) && $this->connectionGetter) {
            $this->connection = ($this->connectionGetter)();
        }

        if (! $this->connection) {
            exception('MissingRedisConnection');
        }

        $db = $this->annotations->meta->DATABASE ?? null;
        if (! $db) {
            exception('MissingDatabaseInRedisAnnotations', uncollect($this->annotations->meta ?? []));
        }
        if (! TypeHint::isUInt($db)) {
            exception('InvalidRedisDatabaseInAnnotations', [$db]);
        }

        $db = TypeHint::convertToUint($db);

        $this->connection->select($db);

        return $this->connection;
    }

    public function annotations()
    {
        return $this->annotations;
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
