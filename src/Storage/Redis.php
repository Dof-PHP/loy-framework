<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

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

    /** @var array: Commands executed in the lifetime of this instance */
    private $cmds = [];
    
    public function __construct(array $annotations = [])
    {
        $this->annotations = collect($annotations);
    }

    public function __call(string $method, array $params = [])
    {
        $this->appendCMD($method, ...$params);

        return $this->getConnection()->{$method}(...$params);
    }

    public function __logging()
    {
        return $this->cmds;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection()
    {
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

    private function appendCMD(...$params)
    {
        $this->cmds[] = fixed_string(join(' ', $params), 255);

        return $this;
    }
}
