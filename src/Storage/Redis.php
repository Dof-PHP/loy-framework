<?php

declare(strict_types=1);

namespace Dof\Framework\Storage;

use Throwable;
use Dof\Framework\Collection;

/**
 * API docs: <https://github.com/phpredis/phpredis/blob/develop/README.markdown>
 */
class Redis implements StorageInterface
{
    private $connection;

    /** @var array: Commands executed in this instance lifetime */
    private $cmds = [];
    
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

    public function callbackOnConnected(Collection $config)
    {
        if ($db = $config->get('database', null)) {
            $this->appendCMD('select', $db);
        }
    }

    public function getConnection()
    {
        if (! $this->connection) {
            exception('MissingRedisConnection');
        }

        return $this->connection;
    }

    private function appendCMD(...$params)
    {
        $this->cmds[] = fixed_string(join(' ', $params), 255);

        return $this;
    }
}
